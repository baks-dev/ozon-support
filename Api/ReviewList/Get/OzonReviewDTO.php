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

namespace BaksDev\Ozon\Support\Api\ReviewList\Get;

use DateTimeImmutable;

/** @see OzonReviewListRequest */
final readonly class OzonReviewDTO
{
    private string $id;

    private int $sku;

    private string $text;

    private DateTimeImmutable $published;

    private int $rating;

    private string $status;

    private int $commentsAmount;

    private int $photosAmount;

    private int $videosAmount;

    private string $orderStatus;

    private bool $isRatingPart;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->sku = $data['sku'];
        $this->text = $data['text'];
        $this->published = new \DateTimeImmutable($data['published_at']);
        $this->rating = $data['rating'];
        $this->status = $data['status'];
        $this->commentsAmount = $data['comments_amount'];
        $this->photosAmount = $data['photos_amount'];
        $this->videosAmount = $data['videos_amount'];
        $this->orderStatus = $data['order_status'];
        $this->isRatingPart = $data['is_rating_participant'];
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

    /** Текст отзыва */
    public function getText(): string
    {
        return $this->text;
    }

    /** Дата публикации отзыва */
    public function getPublished(): DateTimeImmutable
    {
        return $this->published;
    }

    /** Оценка отзыва */
    public function getRating(): int
    {
        return $this->rating;
    }

    /** Статус отзыва
     * — UNPROCESSED — не обработан,
     * — PROCESSED — обработан
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /** Количество комментариев у отзыва */
    public function getCommentsAmount(): int
    {
        return $this->commentsAmount;
    }

    /** Количество изображений у отзыва */
    public function getPhotosAmount(): int
    {
        return $this->photosAmount;
    }

    /** Количество видео у отзыва */
    public function getVideosAmount(): int
    {
        return $this->videosAmount;
    }

    /** Статус заказа, на который покупатель оставил отзыв:
     *
     * — DELIVERED — доставлен,
     * — CANCELLED — отменён
     */
    public function getOrderStatus(): string
    {
        return $this->orderStatus;
    }

    /** Участвует отзыв в подсчёте рейтинга:
     *
     * - true
     * - false
     */
    public function isRatingPart(): bool
    {
        return $this->isRatingPart;
    }
}
