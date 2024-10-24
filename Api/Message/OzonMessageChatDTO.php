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

namespace BaksDev\Ozon\Support\Api\Message;

use DateTimeImmutable;

final readonly class OzonMessageChatDTO
{
    /** Идентификатор сообщения. */
    private string $id;

    /** Идентификатор участника чата. */
    private string $user;

    /**
     * Тип участника чата:
     *
     * customer — покупатель,
     * seller — продавец,
     * crm — системные сообщения,
     * courier — курьер,
     * support — поддержка.
     */
    private string $userType;

    /** Дата создания сообщения */
    private ?DateTimeImmutable $created;

    /** Прочитано ли сообщение. */
    private bool $read;

    /** Массив с содержимым сообщения в формате Markdown.  */
    private array $data;

    public function __construct(array $data)
    {
        $this->id = (string) $data['message_id'];
        $this->user = $data['user']['id'];
        $this->userType = $data['user']['type'];
        $this->created = new DateTimeImmutable($data['created_at']);
        $this->read = $data['is_read'];
        $this->data = $data['data'];
    }

    /** Идентификатор сообщения. */
    public function getId(): string
    {
        return $this->id;
    }

    /** Идентификатор участника чата. */
    public function getUser(): string
    {
        return $this->user;
    }

    /** Тип участника чата */
    public function getUserType(): string
    {
        return $this->userType;
    }

    /** Дата создания сообщения */
    public function getCreated(): ?DateTimeImmutable
    {
        return $this->created;
    }

    /** Прочитано ли сообщение. */
    public function isRead(): bool
    {
        return $this->read;
    }

    /** Массив с содержимым сообщения в формате Markdown.  */
    public function getData(): array
    {
        return $this->data;
    }
}
