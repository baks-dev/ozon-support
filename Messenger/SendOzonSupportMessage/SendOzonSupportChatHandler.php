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

namespace BaksDev\Ozon\Support\Messenger\SendOzonSupportMessage;

use BaksDev\Ozon\Support\Api\Message\Send\SendOzonChatMessageRequest;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 */
#[AsMessageHandler]
final readonly class SendOzonSupportChatHandler
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $ozonSupport,
        private SendOzonChatMessageRequest $sendMessageRequest,
        private CurrentSupportEventRepository $currentSupportEvent,

    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(SupportMessage $message): void
    {

        $chat = new SupportDTO();

        $supportEvent = $this->currentSupportEvent
            ->forSupport($message->getId())
            ->execute();

        $supportEvent->getDto($chat);

        if($chat->getStatus()->getSupportStatus() instanceof SupportStatusClose)
        {

            /** @var SupportMessageDTO $lastMessage */
            $lastMessage = $chat->getMessages()->last();

            //        $this->sendMessageRequest
            //            ->chatId($chat->getInvariable()->getTicket())
            //            ->message($lastMessage->getMessage());


            /** DEBUG */

            $this->logger->critical(
                'json - '. 'chat_id: ' . $chat->getInvariable()->getTicket(). ' | text: ' . $lastMessage->getMessage(),
                [__FILE__.':'.__LINE__],
            );

            $json = [
                "json" => [
                    'chat_id' => $chat->getInvariable()->getTicket(),
                    'text' => $lastMessage->getMessage(),
                ]
            ];
            dump($json);

            /** DEBUG */
        }

        /** DEBUG */
        if($chat->getStatus()->getSupportStatus() instanceof SupportStatusOpen)
        {

            $this->logger->debug(
                'log',
                [__FILE__.':'.__LINE__],
            );

            dump('---без ответа---');
        }
        /** DEBUG */

        dump('-----SendOzonSupportChatHandler------');

    }
}

