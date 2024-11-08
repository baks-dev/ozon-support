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
use BaksDev\Ozon\Support\Api\Post\MarkReading\MarkReadingOzonMessageChatRequest;
use BaksDev\Ozon\Support\Type\OzonSupportProfileType;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
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
        private CurrentSupportEventRepository $currentSupportEvent,
        private MarkReadingOzonMessageChatRequest $markReadingOzonMessageChatRequest,
        private MessageDispatchInterface $messageDispatch
    )
    {
        $this->logger = $ozonSupport;
    }

    /**
     * Делаем прочитанным чат с сообщениями
     */
    public function __invoke(SupportMessage $message): void
    {
        $supportDTO = new SupportDTO();

        $supportEvent = $this->currentSupportEvent
            ->forSupport($message->getId())
            ->find();

        if(false === $supportEvent)
        {
            $this->logger->critical(
                sprintf('Ошибка получения события по идентификатору : %s', $message->getId()),
                [__FILE__.':'.__LINE__],
            );

            return;
        }

        $supportEvent->getDto($supportDTO);
        $SupportInvariableDTO = $supportDTO->getInvariable();

        /** Если событие изменилось - Invariable равен null  */
        if(is_null($SupportInvariableDTO))
        {
            $this->logger->warning(
                sprintf('Ошибка получения Invariable события по идентификатору : %s', $message->getId()),
                [__FILE__.':'.__LINE__],
            );

            return;
        }

        /** @var SupportMessageDTO $lastMessage */
        $lastMessage = $supportDTO->getMessages()->last();

        // проверяем тип профиля
        $typeProfile = $SupportInvariableDTO->getType();

        if(false === $typeProfile->equals(OzonSupportProfileType::TYPE))
        {
            return;
        }

        // проверяем наличие внешнего ID - обязательно для сообщений, поступающий от Ozon API
        if(null === $lastMessage->getExternal())
        {
            return;
        }

        $lastMessageId = (int) $lastMessage->getExternal();

        // отправляем запрос на прочтение
        $result = $this->markReadingOzonMessageChatRequest
            ->chatId($SupportInvariableDTO->getTicket())
            ->fromMessage($lastMessageId)
            ->markReading();

        if(false === $result)
        {
            $this->messageDispatch->dispatch(
                $message,
                [new MessageDelay('1 minutes')],
                'ozon-support'
            );
        }
    }
}

