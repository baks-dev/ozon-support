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

use BaksDev\Ozon\Support\Api\Message\OzonMessageChatDTO;
use BaksDev\Ozon\Support\Api\Message\Post\Send\SendOzonChatMessageRequest;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * // @TODO добавить описание
 */
#[AsMessageHandler]
final class SendOzonMessageChatHandler
{
    private LoggerInterface $logger;

    private ?\DateTimeImmutable $lastMessageDate = null;

    public function __construct(
        LoggerInterface $ozonSupport,
        private CurrentSupportEventRepository $currentSupportEvent,
        private SendOzonChatMessageRequest $sendMessageRequest,
    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(SupportMessage $message): void
    {
        dump('-----SendOzonMessageChatHandler------');

        $supportDTO = new SupportDTO();

        $supportEvent = $this->currentSupportEvent
            ->forSupport($message->getId())
            ->execute();

        $supportEvent->getDto($supportDTO);

        // ответы закрывают чат - реагируем на статус SupportStatusClose
        if($supportDTO->getStatus()->getSupportStatus() instanceof SupportStatusClose)
        {
            /** @var OzonMessageChatDTO $lastMessage */
            $lastMessage = $supportDTO->getMessages()->last();

            // @TODO для прода
            //                    $this->sendMessageRequest
            //                        ->chatId($supportDTO->getInvariable()->getTicket())
            //                        ->message($lastMessage);

        }
    }
}

