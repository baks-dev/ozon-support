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

namespace BaksDev\Ozon\Support\Messenger\MarkOzonMessageChatReading;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Support\Api\Message\Post\MarkReading\MarkReadingOzonMessageChatRequest;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При добавлении новых сообщений в чат:
 * - получаем текущее событие чата;
 * - получаем последнее добавленное сообщение;
 * - отправляем запрос на прочтение всех сообщений, после последнего добавленного сообщения
 * - в случае ошибки OZON API повторяем текущий процесс через интервал времени
 */
#[AsMessageHandler]
final readonly class MarkReadingOzonMessageChatHandler
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $ozonSupport,
        private MessageDispatchInterface $messageDispatch,
        private CurrentSupportEventRepository $currentSupportEvent,
        private MarkReadingOzonMessageChatRequest $markReadingOzonMessageChatRequest,
    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(SupportMessage $message): void
    {
        $supportDTO = new SupportDTO();

        $supportEvent = $this->currentSupportEvent
            ->forSupport($message->getId())
            ->execute();

        $supportEvent->getDto($supportDTO);

        /** @var SupportMessageDTO $lastMessage */
        $lastMessage = $supportDTO->getMessages()->last();
        $lastMessageId = (int) $lastMessage->getExternal();

        // отправляем запрос на прочтение
        $result = $this->markReadingOzonMessageChatRequest
            ->chatId($supportDTO->getInvariable()->getTicket())
            ->fromMessage($lastMessageId)
            ->markReading();

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
                    // задержка 10 минут для отметки выбранного сообщения и сообщений до него прочитанными
                    stamps: [new MessageDelay(DateInterval::createFromDateString('10 minutes'))],
                    transport: (string) $profile,
                );
        }
    }
}

