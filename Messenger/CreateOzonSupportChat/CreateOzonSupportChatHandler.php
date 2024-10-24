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

namespace BaksDev\Ozon\Support\Messenger\CreateOzonSupportChat;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Support\Api\Chat\List\GetOzonChatListRequest;
use BaksDev\Ozon\Support\Api\Chat\OzonChatDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * - получаем активные токены Ozon по профилю пользователя
 * - делаем запрос на открытые чаты
 * - бросаем сообщения для создания чата техподдержки с Ozon
 */
#[AsMessageHandler]
final readonly class CreateOzonSupportChatHandler
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $ozonSupport,
        private MessageDispatchInterface $messageDispatch,
        private GetOzonChatListRequest $ozonChatListRequest,
    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(CreateOzonSupportChatMessage $message): void
    {
        /** Получаем ID профиля */
        $profile = $message->getProfile();

        /** Получаем массив чатов по профилю */
        $chats = $this->ozonChatListRequest
            ->profile($profile)
            ->opened()
            ->unreadMessageOnly()
            ->get();

        /** @var array<int, OzonChatDTO> $chats */
        $chats = iterator_to_array($chats);

        // только чаты с покупателями
        $customerChats = array_filter($chats, function(OzonChatDTO $chat) {
            return $chat->getType() === 'Buyer_Seller';
        });

        $sellerChats = array_filter($chats, function(OzonChatDTO $chat) {
            return str_starts_with($chat->getType(), 'Seller');
        });


        foreach($customerChats as $customerChat)
        {
            $this->messageDispatch->dispatch(
                message: new CreateOzonSupportChatMessage($profile),
                transport: (string) $profile,
            );
        }


    }
}

