<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Ozon\Support\Messenger\ReplyMessage;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Repository\OzonTokensByProfile\OzonTokensByProfileInterface;
use BaksDev\Ozon\Support\Api\Post\SendMessage\SendOzonMessageChatRequest;
use BaksDev\Ozon\Support\Type\OzonSupportProfileType;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendOzonMessageChatDispatcher
{
    public function __construct(
        #[Target('ozonSupportLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private CurrentSupportEventRepository $currentSupportEvent,
        private SendOzonMessageChatRequest $sendMessageRequest,
        private OzonTokensByProfileInterface $OzonTokensByProfile,
    ) {}

    /**
     * При ответе на пользовательские сообщения:
     * - получаем текущее событие чата;
     * - проверяем статус чата - наши ответы закрывают чат - реагируем на статус SupportStatusClose;
     * - отправляем последнее добавленное сообщение - наш ответ;
     * - в случае ошибки OZON API повторяем текущий процесс через интервал времени.
     */
    public function __invoke(SupportMessage $message): void
    {

        $CurrentSupportEvent = $this->currentSupportEvent
            ->forSupport($message->getId())
            ->find();

        if(false === ($CurrentSupportEvent instanceof SupportEvent))
        {
            $this->logger->critical(
                'ozon-support: Ошибка получения события по идентификатору :'.$message->getId(),
                [self::class.':'.__LINE__],
            );

            return;
        }

        $UserProfileUid = $CurrentSupportEvent->getInvariable()?->getProfile();

        if(false === ($UserProfileUid instanceof UserProfileUid))
        {
            $this->logger->critical(
                sprintf('ozon-support: Ошибка получения профиля по идентификатору : %s', $message->getId()));

            return;

        }

        /** @var SupportDTO $SupportDTO */

        $SupportDTO = $CurrentSupportEvent->getDto(SupportDTO::class);

        $SupportInvariableDTO = $SupportDTO->getInvariable();

        if(false === ($SupportInvariableDTO instanceof SupportInvariableDTO))
        {
            return;
        }

        // проверяем тип профиля
        $typeProfile = $SupportInvariableDTO->getType();

        if(false === $typeProfile->equals(OzonSupportProfileType::TYPE))
        {
            return;
        }

        /** Проверяем что чат закрыт */
        if(false === $SupportDTO->getStatus()->equals(SupportStatusClose::class))
        {
            return;
        }

        /** @var SupportMessageDTO $lastMessage */
        $lastMessage = $SupportDTO->getMessages()->last();

        // проверяем наличие внешнего ID - для наших ответов его быть не должно
        if(null !== $lastMessage->getExternal())
        {
            return;
        }

        $lastMessageText = $lastMessage->getMessage();
        $externalChatId = $SupportInvariableDTO->getTicket();

        $result = $this->sendMessageRequest
            ->forTokenIdentifier($UserProfileUid)
            ->chatId($externalChatId)
            ->message($lastMessageText)
            ->sendMessage();

        if(false === $result)
        {
            $this->logger->warning(
                'Повтор выполнения сообщения через 1 минут',
                [self::class.':'.__LINE__],
            );

            $this->messageDispatch
                ->dispatch(
                    message: $message,
                    // задержка 1 минуту для отправки сообщение в существующий чат по его идентификатору
                    stamps: [new MessageDelay('1 minutes')],
                    transport: 'ozon-support',
                );
        }

    }
}
