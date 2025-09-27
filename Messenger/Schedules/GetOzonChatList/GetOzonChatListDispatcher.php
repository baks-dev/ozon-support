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

namespace BaksDev\Ozon\Support\Messenger\Schedules\GetOzonChatList;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Repository\OzonTokensByProfile\OzonTokensByProfileInterface;
use BaksDev\Ozon\Support\Api\Get\ChatList\GetOzonChatListRequest;
use BaksDev\Ozon\Support\Messenger\Schedules\GetOzonChatMessages\GetOzonCustomerMessageChatMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Получает новые сообщения Ozon
 *
 * Получаем профиль пользователя с активным токеном на Ozon:
 *
 * - делаем запрос на получение списка чатов: открытые и с непрочитанными сообщениями
 * - фильтруем только чаты с покупателями
 * - получаем сообщения чата: от новых к старым
 * - добавляем новые сообщения в чат
 * - создаем/обновляем чат техподдержки (Support)
 */
#[AsMessageHandler]
final readonly class GetOzonChatListDispatcher
{
    public function __construct(
        private MessageDispatchInterface $messageDispatch,
        private GetOzonChatListRequest $ozonChatListRequest,
        private OzonTokensByProfileInterface $OzonTokensByProfile,
    ) {}

    public function __invoke(GetOzonChatListMessage $message): void
    {
        $profile = $message->getProfile();

        /** Получаем все токены профиля */

        $tokensByProfile = $this->OzonTokensByProfile
            ->forProfile($message->getProfile())
            ->onlyCardUpdate()
            ->findAll();

        if(false === $tokensByProfile || false === $tokensByProfile->valid())
        {
            return;
        }

        foreach($tokensByProfile as $OzonTokenUid)
        {
            /**
             * Получаем массив чатов:
             * - открытые
             * - с непрочитанными сообщениями
             */
            $listChats = $this->ozonChatListRequest
                ->forTokenIdentifier($OzonTokenUid)
                ->unreadMessageOnly() // Только чаты с непрочитанными сообщениями
                ->opened() // только открытые чаты
                ->getListChats();


            // в случае ошибки при запросе
            if(false === $listChats || false === $listChats->valid())
            {
                continue;
            }

            foreach($listChats as $customerChat)
            {
                // только чаты с покупателями
                if($customerChat->getType() !== 'BUYER_SELLER')
                {
                    continue;
                }

                $GetOzonCustomerMessageChatMessage = new GetOzonCustomerMessageChatMessage(
                    chatId: $customerChat->getId(),
                    profile: $profile,
                    identifier: $OzonTokenUid,
                );

                $this->messageDispatch->dispatch(
                    message: $GetOzonCustomerMessageChatMessage,
                    transport: (string) $profile,
                );
            }
        }
    }
}

