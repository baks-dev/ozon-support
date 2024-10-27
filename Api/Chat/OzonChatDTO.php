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
 *
 */

declare(strict_types=1);

namespace BaksDev\Ozon\Support\Api\Chat;

use DateTimeImmutable;

final readonly class OzonChatDTO
{
    /** Идентификатор чата */
    private string $id;

    /** Статус чата */
    private string $status;

    /** Тип чата */
    private string $type;

    /** Дата создания чата */
    private DateTimeImmutable $created;

    /** Идентификатор первого непрочитанного сообщения в чате. */
    private int $firstUnreadMessage;

    /** Количество непрочитанных сообщений в чате. */
    private int $unreadMessageCount;

    /** Идентификатор последнего сообщения в чате. */
    private int $lastMessage;

    public function __construct(array $data)
    {
        $this->id = $data['chat_id'];
        $this->status = $data['chat_status'];
        $this->type = $data['chat_type'];
        $this->created = new \DateTimeImmutable($data['created_at']);
        $this->firstUnreadMessage = $data['first_unread_message_id'];
        $this->unreadMessageCount = $data['unread_count'];
        $this->lastMessage = $data['last_message_id'];
    }

    /** Идентификатор чата */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Статус чата:
     *
     * All — все чаты.
     * Opened — открытые чаты.
     * Closed — закрытые чаты.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Тип чата
     *
     * Seller_Support — чат с поддержкой.
     * Buyer_Seller — чат с покупателем.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /** Дата создания чата */
    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    /** Идентификатор первого непрочитанного сообщения в чате. */
    public function getFirstUnreadMessage(): int
    {
        return $this->firstUnreadMessage;
    }

    /** Идентификатор последнего сообщения в чате. */
    public function getLastMessage(): int
    {
        return $this->lastMessage;
    }

    /** Идентификатор последнего сообщения в чате. */
    public function getUnreadMessageCount(): int
    {
        return $this->unreadMessageCount;
    }
}
