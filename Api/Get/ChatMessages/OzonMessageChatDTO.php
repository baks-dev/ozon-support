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

namespace BaksDev\Ozon\Support\Api\Get\ChatMessages;

use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;

final readonly class OzonMessageChatDTO
{
    /** Идентификатор сообщения. */
    private string $id;

    /** Идентификатор участника чата. */
    private string $userId;

    /** Тип участника чата */
    private string $userType;

    /** Дата создания сообщения */
    private ?DateTimeImmutable $created;

    /** Прочитано ли сообщение. */
    private bool $read;

    /** Массив с содержимым сообщения в формате Markdown.  */
    private string $data;

    /** ВНУТРЕННИЙ ПАРАМЕТР. Заголовок сообщений о возврате. */
    private ?string $refundTitle;

    /**
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     */
    public function __construct(array $data)
    {
        $this->id = (string) $data['message_id'];
        $this->userId = $data['user']['id'];
        $this->userType = $data['user']['type'];
        $this->read = $data['is_read'];

        $moscowTimezone = new DateTimeZone(date_default_timezone_get());
        $this->created = new DateTimeImmutable($data['created_at'])->setTimezone($moscowTimezone);

        $message = $data['data'];

        // Признак, что сообщение содержит изображение

        if($data['is_image'])
        {
            $this->refundTitle = null;
            $this->data = current($message);
            return;
        }

        $this->refundTitle = null;
        $message = implode(PHP_EOL, str_replace(['  ', PHP_EOL], ' ', $message));
        $this->data = str_replace('  ', ' ', $message);

    }

    /** Идентификатор сообщения. */
    public function getId(): string
    {
        return $this->id;
    }

    /** Идентификатор участника чата. */
    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * Тип участника чата:
     *
     * Customer — покупатель,
     * Seller — продавец,
     * Crm — системные сообщения,
     * Courier — курьер,
     * Support — поддержка.
     */
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

    /** Массив с содержимым сообщения в формате Markdown. */
    public function getData(): string
    {
        $imageLink = false;

        // 1 - начало для ссылок на изображения от api ozon.

        // [Screenshot_... (...KB)](https://api-seller.ozon.ru/v2/chat/file/.../....jpg)
        if(stripos($this->data, 'Screenshot') !== false)
        {
            preg_match('/((.*?))/', $this->data, $apiLinkMatches);
            empty($apiLinkMatches[1]) ?: $imageLink = $apiLinkMatches[1];
        }

        // Формат для ссылок api - ![](ссылка)
        if(str_starts_with($this->data, '!'))
        {
            preg_match('~\(\K.+?(?=\))~', $this->data, $apiLinkMatches);
            empty($apiLinkMatches) ?: $imageLink = $apiLinkMatches[0];
        }


        if($imageLink)
        {
            $pathInfo = pathinfo($imageLink);


            //            $this->ticket, // идентификатор чата (тикета)
            //            $this->message, // идентификатор сообщения // message_id
            //            $this->file // файл

            /**
             * @see FileController
             */
            $url = '/admin/ozon-support/files/'.$pathInfo['basename'].'/info';

            // миниатюра картинки
            $miniature = sprintf('<img src="%s" width="200" height="auto">', $url);

            // ссылка на полноразмерное изображение
            $link = sprintf('<a href="%s" class="ms-3" target="_blank">Открыть полное фото</a>', $url);

            return $miniature.' '.$link;
        }


        // ищем ссылку в фигурных скобках. Формат для ссылок - [текст](ссылка)
        //preg_match('~\(\K.+?(?=\))~', $this->data, $linkMatches);
        preg_match('/\[[^\]]*\]\((https?:\/\/[^\s)]+)\)/', $this->data, $linkMatches);

        if(false === empty($linkMatches))
        {
            return sprintf('<a href="%s" target="_blank">Ссылка<a/>', $linkMatches[0]);
        }

        // обычный текст
        return $this->data;
    }

    /** ВНУТРЕННИЙ ПАРАМЕТР. Заголовок сообщений о возврате. */
    public function getRefundTitle(): ?string
    {
        return $this->refundTitle;
    }
}
