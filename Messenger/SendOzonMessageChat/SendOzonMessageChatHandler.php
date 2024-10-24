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

namespace BaksDev\Ozon\Support\Messenger\SendOzonMessageChat;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Support\Api\Post\SendMessage\SendOzonMessageChatRequest;
use BaksDev\Ozon\Support\Type\OzonSupportProfileType;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При ответе на пользовательские сообщения:
 * - получаем текущее событие чата;
 * - проверяем статус чата - наши ответы закрывают чат - реагируем на статус SupportStatusClose;
 * - отправляем последнее добавленное сообщение - наш ответ;
 * - в случае ошибки OZON API повторяем текущий процесс через интервал времени.
 */
#[AsMessageHandler]
final readonly class SendOzonMessageChatHandler
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $ozonSupport,
        private MessageDispatchInterface $messageDispatch,
        private CurrentSupportEventRepository $currentSupportEvent,
        private SendOzonMessageChatRequest $sendMessageRequest,
    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(SupportMessage $message): void
    {
        $supportDTO = new SupportDTO();

        $supportEvent = $this->currentSupportEvent
            ->forSupport($message->getId())
            ->find();

        if(false === $supportEvent)
        {
            $this->logger->critical(
                'Ошибка получения события по идентификатору :'.$message->getId(),
                [__FILE__.':'.__LINE__],
            );

            return;
        }

        $supportEvent->getDto($supportDTO);

        // проверяем тип профиля
        $typeProfile = $supportDTO->getInvariable()->getType();

        if(false === $typeProfile->equals(OzonSupportProfileType::TYPE))
        {
            $this->logger->critical(
                'Идентификатор профиля не соответствует типу профиля: OzonSupportProfileType'.'| Переданный идентификатор: '.(string) $typeProfile,
                [__FILE__.':'.__LINE__],
            );

            return;
        }

        // ответы закрывают чат - реагируем на статус SupportStatusClose
        if($supportDTO->getStatus()->getSupportStatus() instanceof SupportStatusClose)
        {
            /** @var SupportMessageDTO $lastMessage */
            $lastMessage = $supportDTO->getMessages()->last();

            // проверяем наличие внешнего ID - для наших ответов его быть не должно
            if(null !== $lastMessage->getExternal())
            {
                $this->logger->critical(
                    'Ответ на сообщение не должен иметь внешний (external) ID',
                    [__FILE__.':'.__LINE__],
                );

                return;
            }

            $lastMessageText = $lastMessage->getMessage();

            $externalChatId = $supportDTO->getInvariable()->getTicket();

            $result = $this->sendMessageRequest
                ->chatId($externalChatId)
                ->message($lastMessageText)
                ->sendMessage();

            if(false === $result)
            {
                $this->logger->warning(
                    'Повтор выполнения сообщения через 10 минут',
                    [__FILE__.':'.__LINE__],
                );

                $profile = $supportDTO->getInvariable()->getProfile();

                $this->messageDispatch
                    ->dispatch(
                        message: $message,
                        // задержка 10 минут для отправки сообщение в существующий чат по его идентификатору
                        stamps: [new MessageDelay(DateInterval::createFromDateString('10 minutes'))],
                        transport: (string) $profile,
                    );
            }
        }
    }
}

