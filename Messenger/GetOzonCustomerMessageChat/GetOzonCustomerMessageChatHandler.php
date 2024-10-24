<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Ozon\Support\Messenger\GetOzonCustomerMessageChat;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Ozon\Orders\Type\ProfileType\TypeProfileFbsOzon;
use BaksDev\Ozon\Support\Api\Chat\Get\History\GetOzonChatHistoryRequest;
use BaksDev\Ozon\Support\Api\Message\OzonMessageChatDTO;
use BaksDev\Ozon\Support\Repository\CurrentSupportByOzonChat\CurrentSupportByOzonChatRepository;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Type\Priority\SupportPriority;
use BaksDev\Support\Type\Priority\SupportPriority\Collection\SupportPriorityHeight;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Получаем профиль пользователя с активным токеном на Ozon:
 *
 * - делаем запрос на получение списка чатов: открытые и с непрочитанными сообщениями
 * - фильтруем только чаты с покупателями
 * - бросаем сообщения для создания чата техподдержки (Support)
 */
#[AsMessageHandler]
final readonly class GetOzonCustomerMessageChatHandler
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $ozonSupport,
        private DeduplicatorInterface $deduplicator,
        private GetOzonChatHistoryRequest $chatHistoryRequest,
        private CurrentSupportByOzonChatRepository $supportByOzonChat,
        private SupportHandler $supportHandler,
    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(GetOzonCustomerMessageChatMessage $message): void
    {
        $ticket = $message->getChatId();
        $profile = $message->getProfile();

        $supportDTO = new SupportDTO();

        // подготавливаю DTO для события
        $supportDTO->setPriority(new SupportPriority(SupportPriorityHeight::PARAM)); // Customer - высокий приоритет
        $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM)); // Для нового сообщения - open

        $supportInvariableDTO = new SupportInvariableDTO();
        $supportInvariableDTO->setProfile($profile);
        $supportInvariableDTO->setType(new TypeProfileUid(TypeProfileFbsOzon::TYPE)); // @TODO ПЕРЕДЕЛАТЬ - добавить тип для Озон

        $supportInvariableDTO->setTicket($message->getChatId());
        $supportInvariableDTO->setTitle('OZON'); // @TODO негде взять
        $supportDTO->setInvariable($supportInvariableDTO);

        // текущее событие чата по идентификатору чата (тикета) из Ozon
        $support = $this->supportByOzonChat->find($ticket);

        if($support)
        {
            // пересохраняю событие с новыми данными
            $support->getDto($supportDTO);
        }

        /** Сообщения чата */
        // получаем массив сообщений из чата
        $messagesChat = $this->chatHistoryRequest
            ->profile($profile)
            ->chatId($ticket)
            ->sortByNew()
            ->limit(1000) // @TODO максимальный лимит?
            ->getMessages();

        // только непрочитанные сообщения
        $messagesChat = array_filter(iterator_to_array($messagesChat), function(OzonMessageChatDTO $message) {
            return false === $message->isRead();
        });

        // @TODO все сообщения, кроме type seller

        //  для отслеживания созданных сообщения в чате
        $deduplicator = $this->deduplicator
            ->namespace('ozon-support')
            ->expiresAfter(DateInterval::createFromDateString('1 minute'));

        /** @var OzonMessageChatDTO $chatMessage */
        foreach($messagesChat as $chatMessage)
        {
            // уникальный ключ сообщения для его проверки существования в текущем чате по данным о сообщении из Ozon
            $deduplicator->deduplication(
                [
                    $ticket,
                    $chatMessage->getId(),
                    $chatMessage->getUser(),
                    $profile,
                    self::class,
                ]
            );

            if($this->deduplicator->isExecuted())
            {
                continue;
            }

            // Формируем сообщение
            $supportMessageDTO = new SupportMessageDTO();
            $supportMessageDTO->setName($chatMessage->getUser());
            $supportMessageDTO->setMessage(current($chatMessage->getData()));

            $supportDTO->addMessage($supportMessageDTO);

            // при добавлении нового сообщения открываем чат заново
            $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));

            $deduplicator->save();
        }

        $result = $this->supportHandler->handle($supportDTO);

        if(false === $result instanceof Support)
        {
            $this->logger->critical(
                sprintf(
                    'ozon-support: Ошибка %s при создании/обновлении чата поддержки:
                         Profile: %s | SupportEvent ID: %s | SupportEventInvariable.Ticker ID: %s',
                    $result,
                    (string) $profile,
                    $supportDTO->getEvent(),
                    $supportDTO->getInvariable()->getTicket(),
                ),
                [__FILE__.':'.__LINE__],
            );
        }
    }
}

