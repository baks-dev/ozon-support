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

namespace BaksDev\Ozon\Support\Api\ReviewComments\Post;

use BaksDev\Ozon\Api\Ozon;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Webmozart\Assert\Assert;

final class PostOzonReviewCommentRequest extends Ozon
{
    private bool $markAsProcessed = false;

    private string|bool $parentCommentId = false;

    private string|bool $reviewId = false;

    private string|bool $text = false;

    /**
     * Обновление статуса у отзыва:
     * - true — статус изменится на Processed.
     * - false — статус не изменится.
     */
    public function markAsProcessed(): self
    {
        $this->markAsProcessed = true;
        return $this;
    }

    /** Идентификатор родительского комментария, на который вы отвечаете */
    public function parentCommentId(string $commentId): self
    {
        $this->parentCommentId = $commentId;
        return $this;
    }

    /** Идентификатор отзыва */
    public function reviewId(string $reviewId): self
    {
        $this->reviewId = $reviewId;
        return $this;
    }

    /** Текст комментария */
    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Оставить комментарий на отзыв
     * @see https://docs.ozon.ru/api/seller/#operation/ReviewAPI_CommentCreate
     */
    public function postReviewComment(): string|bool
    {
        /** Выполнять операции запроса ТОЛЬКО в PROD окружении! */
        if($this->isExecuteEnvironment() === false)
        {
            return true;
        }

        if(false === $this->reviewId)
        {
            throw new InvalidArgumentException('Не передан параметр запроса $reviewId');
        }

        if(false === $this->text)
        {
            throw new InvalidArgumentException('Не передан параметр запроса $text');
        }

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                'v1/review/comment/create',
                [
                    "json" => [
                        "mark_review_as_processed" => $this->markAsProcessed,
                        "parent_comment_id" => $this->parentCommentId ?: null,
                        "review_id" => $this->reviewId,
                        "text" => $this->text,
                    ]
                ]
            );

        $result = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {

            if(str_contains($result['message'], 'Premium Plus'))
            {
                return true;
            }

            $message = sprintf('ozon-support: Код ответа: %s. Ошибка получения списка комментариев на отзыв от Ozon Seller API', $response->getStatusCode());

            $this->logger->critical(
                message: $message,
                context: [
                    self::class.':'.__LINE__,
                    $result
                ]);

            return false;
        }

        if(isset($result['code']))
        {
            $message = sprintf('ozon-support: Код ошибки: %s. Ошибка получения информации об отзыве от Ozon Seller API', $result['code']);

            $this->logger->critical(
                message: $message,
                context: [
                    self::class.':'.__LINE__,
                    $result
                ]);

            return false;
        }


        return $result['comment_id'];
    }
}
