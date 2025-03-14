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
 *
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
        $this->published = new \DateTimeImmutable($data['published_at']);
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

    /** Статус заказа, на который покупатель оставил отзыв:
     * — DELIVERED — доставлен,
     * — CANCELLED — отменён.
     * */
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
        $link = sprintf('<a href="https://www.ozon.ru/product/%s" class="ms-3">Ссылка на товар<a/>%s', $this->sku, PHP_EOL);

        $text = empty($this->text) ? '<strong><i>Текст отзыва отсутствует</i></strong>' : $this->text;

        $photos = '';

        foreach($this->photos as $photo)
        {
            $url = $photo['url'];

            $photos .= sprintf('<a href="%s" class="ms-3" target="_blank">вложение (фото)<a/>%s', $url, PHP_EOL);
        }

        $div = sprintf('%s<div>%s</div>%s<div>%s</div>', $link, $text, PHP_EOL, $photos);

        return $div;
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

        return sprintf('<span class="badge text-bg-%s align-middle">%s</span>', $rating, (string) $this->rating);
    }


}
