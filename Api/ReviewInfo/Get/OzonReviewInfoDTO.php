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

namespace BaksDev\Ozon\Support\Api\ReviewInfo\Get;

use DateTimeImmutable;
use function PHPUnit\Framework\returnArgument;

/** @see GetOzonReviewInfoRequest */
final readonly class OzonReviewInfoDTO
{
    private string $id;

    private int $sku;

    private int $rating;

    private array $photos;

    private string $text;

    private string $orderStatus;

    private DateTimeImmutable $published;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->sku = $data['sku'];
        $this->rating = $data['rating'];
        $this->photos = $data['photos'];
        $this->text = $data['text'];
        $this->orderStatus = $data['order_status'];
        $this->published = new DateTimeImmutable($data['published_at']);
    }

    /** Идентификатор отзыва */
    public function getId(): string
    {
        return $this->id;
    }

    /** Идентификатор товара в системе Ozon — SKU */
    public function getSku(): int
    {
        return $this->sku;
    }

    /** Оценка отзыва */
    public function getRating(): int
    {
        return $this->rating;
    }

    /** Информация об изображении */
    public function getPhotos(): array
    {
        return $this->photos;
    }

    /** Текст отзыва */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Статус заказа, на который покупатель оставил отзыв:
     * — DELIVERED — доставлен,
     * — CANCELLED — отменён.
     */
    public function getOrderStatus(): string
    {
        return match ($this->orderStatus)
        {
            'DELIVERED' => 'ДОСТАВЛЕН',
            'CANCELLED' => 'ОТМЕНЕН',
            default => 'НЕИЗВЕСТНЫЙ СТАТУС',
        };
    }

    /** Дата публикации комментария */
    public function getPublished(): DateTimeImmutable
    {
        return $this->published;
    }

    /** Собирает единое сообщение из всех вложенностей в отзыв */
    public function getMessage(): string
    {
        // ссылка на отзыв
        $message = sprintf('<p><a href="https://www.ozon.ru/product/%s" class="ms-3" target="_blank">Ссылка на товар</a></p>', $this->sku);

        // текст сообщения
        $message .= empty($this->text) ? '<strong><i>Текст отзыва отсутствует</i></strong>' : $this->text;

        if(false === empty($this->photos))
        {
            $message .= '<div class="d-flex gap-3 mt-3">';

            foreach($this->photos as $photo)
            {
                $url = $photo['url'];

                $message .= sprintf('<a href="%s" class="ms-3" target="_blank">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-image" viewBox="0 0 16 16">
                      <path d="M8.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0"/>
                      <path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M3 2a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v8l-2.083-2.083a.5.5 0 0 0-.76.063L8 11 5.835 9.7a.5.5 0 0 0-.611.076L3 12z"/>
                    </svg>
                </a>', $url);
            }

            $message .= '</div>';
        }

        return $message;
    }

    /** Метод формирует тему сообщения */
    public function getTitle(): string
    {
        $rating = match ($this->rating)
        {
            1, 2 => 'danger',
            3, 4 => 'warning',
            5 => 'success',
            default => 'без рейтинга',
        };

        return sprintf('<span class="badge text-bg-%s align-middle">%s</span> &nbsp; %s', $rating, $this->rating, $this->getOrderStatus());
    }
}