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

namespace BaksDev\Ozon\Support\Api\Message\Inbox;

use BaksDev\Ozon\Api\Ozon;
use DomainException;
use Generator;

/**
 * Возвращает информацию о чатах по указанным фильтрам.
 * @see https://docs.ozon.ru/api/seller/#operation/ChatAPI_ChatListV2
 */
final class OzonChatListRequest extends Ozon
{

    /** Все чаты (по умолчанию) */
    private string $status = 'All';

    private function status(?bool $status): self
    {
        if($status !== null)
        {

            /** Закрытые чаты */
            if($status === false)
            {
                $this->status = 'Closed';
            }

            /** Открытые чаты */
            if($status === true)
            {
                $this->status = 'Opened';
            }
        }

        return $this;
    }


    public function findAll(?bool $unread = true, ?bool $status = null): Generator
    {
        $this->status($status);

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                '/v2/chat/list',
                [
                    "json" => [
                        /** Фильтр по чатам */
                        'filter' => [
                            "chat_status" => $this->status,
                            "unread_only" => $unread
                        ],
                        /** Количество значений в ответе.  */
                        "limit" => 1,

                        /** Количество элементов, которое будет пропущено в ответе.
                         * Например, если offset=10, ответ начнётся с 11-го найденного элемента.
                         */
                        "offset" => 0
                    ]
                ]
            );


        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical($content['code'].': '.$content['message'], [__FILE__.':'.__LINE__]);

            throw new DomainException(
                message: 'Ошибка '.self::class,
                code: $response->getStatusCode()
            );
        }

        foreach($content['chats'] as $chat)
        {
            yield new OzonChatListDTO($chat);
        }
    }

}
