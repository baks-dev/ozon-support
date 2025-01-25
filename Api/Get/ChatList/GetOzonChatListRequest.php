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

namespace BaksDev\Ozon\Support\Api\Get\ChatList;

use BaksDev\Ozon\Api\Ozon;
use Generator;

/**
 * Возвращает информацию о чатах по указанным фильтрам.
 */
final class GetOzonChatListRequest extends Ozon
{
    /**
     * Фильтр по статусу чата:
     *
     * All — все чаты.
     * Opened — открытые чаты.
     * Closed — закрытые чаты.
     *
     * Значение по умолчанию - все чаты
     */
    private string $status = 'All';

    /**
     * Фильтр по чатам с непрочитанными сообщениями
     *
     * Значение по умолчанию - все чаты
     */
    private bool $unreadOnly = false;

    /**
     * Количество значений в ответе.
     *
     * Значение по умолчанию — 30
     * Максимальное значение — 1000.
     */
    private int $limit = 30;

    /**
     * Количество элементов, которое будет пропущено в ответе.
     * Например, если offset=10, ответ начнётся с 11-го найденного элемента.
     *
     * Значение по умолчанию — 0
     */
    private int $offset = 0;

    /** Фильтр по статусу чата: только открытые чаты. */
    public function opened(): self
    {
        $this->status = 'Opened';

        return $this;
    }

    /** Фильтр по статусу чата: только закрытые чаты. */
    public function closed(): self
    {
        $this->status = 'Closed';

        return $this;
    }

    /** Только чаты с непрочитанными сообщениями */
    public function unreadMessageOnly(): self
    {
        $this->unreadOnly = true;

        return $this;
    }

    /** Количество значений в ответе. */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /** Количество элементов, которое будет пропущено в ответе. */
    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Список чатов
     * @see https://docs.ozon.ru/api/seller/#operation/ChatAPI_ChatListV2
     *
     * @return Generator<int, OzonChatDTO>|false
     */
    public function getListChats(): false|Generator
    {
        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                'v2/chat/list',
                [
                    "json" => [
                        'filter' => [
                            "chat_status" => $this->status,
                            "unread_only" => $this->unreadOnly
                        ],
                        "limit" => $this->limit,
                        "offset" => $this->offset
                    ]
                ]
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('ozon-support: Ошибка получения списка чатов от Ozon Seller API'),
                [
                    self::class.':'.__LINE__,
                    $content
                ]);

            return false;
        }

        foreach($content['chats'] as $chat)
        {
            yield new OzonChatDTO($chat);
        }
    }
}
