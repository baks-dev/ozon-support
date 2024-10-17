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

namespace BaksDev\Ozon\Support\Api\Message\History;

use DateTimeImmutable;

final readonly class OzonChatHistoryDTO
{
    /** Идентификатор сообщения. */
    private int $id;

    /** Идентификатор участника чата. */
    private string $userId;

    /**
     * Тип участника чата:
     *
     * customer — покупатель,
     * seller — продавец,
     * crm — системные сообщения,
     * courier — курьер,
     * support — поддержка.
     */
    private string $type;

    /** Дата создания сообщения */
    private ?DateTimeImmutable $created;

    /** Признак, что сообщение прочитано. */
    private bool $read;

    /** Массив с содержимым сообщения в формате Markdown.  */
    private array $data;


    public function __construct(array $data)
    {
        $this->id = $data['message_id'];
        $this->userId = $data['user']['id'];
        $this->type = $data['user']['type'];
        $this->created = new DateTimeImmutable($data['created_at']);
        $this->read = $data['is_read'];
        $this->data = $data['data'];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getCreated(): ?DateTimeImmutable
    {
        return $this->created;
    }

    public function isRead(): bool
    {
        return $this->read;
    }

    public function getData(): array
    {
        return $this->data;
    }

}
