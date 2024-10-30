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

namespace BaksDev\Ozon\Support\Messenger\Schedules\GetOzonChatList;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Support\Api\Chat\Get\List\GetOzonChatListRequest;
use BaksDev\Ozon\Support\Api\Chat\OzonChatDTO;
use BaksDev\Ozon\Support\Messenger\Schedules\GetOzonCustomerMessageChat\GetOzonCustomerMessageChatMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Получаем профиль пользователя с активным токеном на Ozon:
 *
 * - делаем запрос на получение списка чатов: открытые и с непрочитанными сообщениями
 * - фильтруем только чаты с покупателями
 * - получаем сообщения чата: от новых к старым
 * - добавляем новые сообщения в чат
 * - создаем/обновляем чат техподдержки (Support)
 */
#[AsMessageHandler]
final readonly class GetOzonChatListHandler
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

    public function __invoke(GetOzonChatListMessage $message): void
    {
        $profile = $message->getProfile();

        /**
         * Получаем массив чатов:
         * - открытые
         * - с непрочитанными сообщениями
         */
        $listChats = $this->ozonChatListRequest
            ->profile($profile)
            ->opened()
            ->unreadMessageOnly()
            ->getListChats();

        // в случае ошибки при запросе
        if(false === $listChats)
        {
            return;
        }

        // если список чатов пустой
        if(false === $listChats->valid())
        {
            $this->logger->warning(
                'Не найдено чатов по выбранным фильтрам',
                [__FILE__.':'.__LINE__],
            );

            return;
        }

        // только чаты с покупателями
        $customerChats = array_filter(iterator_to_array($listChats), function(OzonChatDTO $chat) {
            return $chat->getType() === 'Buyer_Seller';
        });

        if(empty($customerChats))
        {
            return;
        }

        /** @var OzonChatDTO $customerChat */
        foreach($customerChats as $customerChat)
        {
            $this->messageDispatch->dispatch(
                message: new GetOzonCustomerMessageChatMessage($customerChat->getId(), $profile),
                transport: (string) $profile,
            );
        }
    }
}

