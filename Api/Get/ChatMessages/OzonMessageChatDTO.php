<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

    /** Идентификатор чата */
    private string $chat;

    /** Идентификатор клиента токена склада */
    private string $seller;

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
    private ?string $returnTitle;


    /**
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     */
    public function __construct(array $data, string $chat, string $seller)
    {
        $this->id = (string) $data['message_id'];
        $this->chat = $chat;
        $this->seller = $seller;
        $this->userId = $data['user']['id'];
        $this->userType = $data['user']['type'];
        $this->read = $data['is_read'];

        $moscowTimezone = new DateTimeZone(date_default_timezone_get());
        $this->created = new DateTimeImmutable($data['created_at'])->setTimezone($moscowTimezone);

        $message = $data['data'];

        // Признак, что сообщение содержит изображение

        if($data['is_image'])
        {
            $this->returnTitle = null;
            $this->data = current($message);
            return;
        }

        $this->returnTitle = null;
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

        /** Обрабатываем файл с ВИДЕО */
        if(str_starts_with($this->data, '[VID'))
        {
            if(preg_match('/\((https?:\/\/[^)]+)\)/', $this->data, $urlMatches))
            {
                $url = $urlMatches[1];

                // Парсим URL и получаем путь
                $path = parse_url($url, PHP_URL_PATH);

                if($path)
                {
                    // Извлекаем имя файла из пути
                    $videoLink = 'https://seller.ozon.ru/api/chat/v2/file/download/'.$this->seller.'/'.$this->chat.'/'.$this->id.'/'.basename($path);

                    /**
                     * миниатюра картинки
                     *
                     * @note Не делать перенос строки, т.к. это приведет к тегу <br>
                     */
                    $miniature = sprintf('<a href="%s" class="ms-3" target="_blank"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-film" viewBox="0 0 16 16"><path d="M0 1a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H1a1 1 0 0 1-1-1zm4 0v6h8V1zm8 8H4v6h8zM1 1v2h2V1zm2 3H1v2h2zM1 7v2h2V7zm2 3H1v2h2zm-2 3v2h2v-2zM15 1h-2v2h2zm-2 3v2h2V4zm2 3h-2v2h2zm-2 3v2h2v-2zm2 3h-2v2h2z"/></svg></a>', $videoLink);

                    // ссылка на видео
                    $link = sprintf('<a href="%s" class="ms-3" target="_blank">Скачать видео для просмотра</a>', $videoLink);

                    return $miniature.' '.$link;

                }
            }
        }


        /** Обрабатываем файл с ФОТО или Screenshot */
        if(str_starts_with($this->data, '![]') || str_starts_with($this->data, '[Screenshot'))
        {
            if(preg_match('/\((https?:\/\/[^)]+)\)/', $this->data, $urlMatches))
            {
                $url = $urlMatches[1];

                // Парсим URL и получаем путь
                $path = parse_url($url, PHP_URL_PATH);

                if($path)
                {
                    // Извлекаем имя файла из пути
                    $imageLink = 'https://seller.ozon.ru/api/chat/v2/file/download/'.$this->seller.'/'.$this->chat.'/'.$this->id.'/'.basename($path);

                    /**
                     * миниатюра картинки
                     *
                     * @note Не делать перенос строки, т.к. это приведет к тегу <br>
                     */
                    $miniature = sprintf('<a href="%s" class="ms-3" target="_blank"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-image" viewBox="0 0 16 16"><path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/><path d="M1.5 2A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2zm13 1a.5.5 0 0 1 .5.5v6l-3.775-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12v.54L1 12.5v-9a.5.5 0 0 1 .5-.5z"/></svg></a>', $imageLink);

                    // ссылка на видео
                    $link = sprintf('<a href="%s" class="ms-3" target="_blank">Скачать фото для просмотра</a>', $imageLink);

                    return $miniature.' '.$link;

                }
            }
        }


        // ищем ссылку в фигурных скобках. Формат для ссылок - [текст](ссылка)
        //preg_match('~\(\K.+?(?=\))~', $this->data, $linkMatches);
        preg_match('/\[[^\]]*\]\((https?:\/\/[^\s)]+)\)/', $this->data, $linkMatches);


        if(isset($linkMatches[1]))

        if(false === empty($linkMatches))
        {
            return sprintf('<a href="%s" target="_blank">Ссылка<a/>', $linkMatches[0]);
        }

        // обычный текст
        return $this->data;
    }

    /** ВНУТРЕННИЙ ПАРАМЕТР. Заголовок сообщений о возврате. */
    public function getReturnTitle(): ?string
    {
        return $this->returnTitle;
    }
}


