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

namespace BaksDev\Ozon\Support\Api\ReviewList\Get;

use BaksDev\Ozon\Api\Ozon;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Webmozart\Assert\Assert;

final class OzonReviewListRequest extends Ozon
{
    public const string STATUS_ALL = 'ALL';
    public const string STATUS_UNPROCESSED = 'UNPROCESSED';
    public const string STATUS_PROCESSED = 'PROCESSED';

    public const string SORT_DESC = 'DESC';
    public const string SORT_ASC = 'ASC';

    private string $lastId = '';

    private string|false $sort = false;

    private string|false $status = false;

    /** Идентификатор последнего отзыва на странице */
    public function lastId(string $lastId): self
    {
        $this->lastId = $lastId;
        return $this;
    }

    /** Направление сортировки:
     * - по возрастанию (ASC) - от старых к новым
     * - по убыванию (DESC) - от новых к старым
     */
    public function sort(string $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    /** Фильтр по статусу отзыва:
     * - все (ALL)
     * - необработанные (UNPROCESSED)
     * - обработанные (PROCESSED)
     */
    public function status(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Возвращает список отзывов
     *
     * @see https://docs.ozon.ru/api/seller/#operation/ReviewAPI_ReviewList
     *
     * @return Generator<OzonReviewDTO>|false
     */
    public function getReviewList(): bool|Generator
    {
        if(false === $this->sort)
        {
            throw new InvalidArgumentException('Не передан параметр запроса $sort');
        }

        if(false === $this->status)
        {
            throw new InvalidArgumentException('Не передан параметр запроса $status');
        }

        $cache = $this->getCacheInit('ozon-support');

        while(true)
        {
            $cacheKey = md5($this->getIdentifier().$this->lastId.self::class);

            $result = $cache->get($cacheKey, function(ItemInterface $item): array|bool {

                $item->expiresAfter(1);

                $response = $this->TokenHttpClient()
                    ->request(
                        'POST',
                        'v1/review/list',
                        [
                            "json" => [
                                "last_id" => $this->lastId,
                                "limit" => 100,
                                "sort_dir" => $this->sort,
                                "status" => $this->status,
                            ],
                        ],
                    );

                $result = $response->toArray(false);

                if($response->getStatusCode() !== 200)
                {
                    /** Только премиуим-подписка */
                    if(str_contains(mb_strtolower($result['message']), 'premium'))
                    {
                        return true;
                    }

                    $this->logger->critical(
                        message: sprintf('ozon-support: Код ответа: %s. Ошибка получения списка отзывов от Ozon Seller API', $response->getStatusCode()),
                        context: [self::class.':'.__LINE__, $result,],
                    );

                    return false;
                }

                if(isset($result['code']))
                {
                    $message = sprintf('ozon-support: Код ошибки: %s. Ошибка получения списка отзывов от Ozon Seller API', $result['code']);

                    $this->logger->critical(
                        message: $message,
                        context: [
                            self::class.':'.__LINE__,
                            $result,
                        ]);

                    return false;
                }

                $item->expiresAfter(3600);

                return $result;
            });

            if(true === is_bool($result))
            {
                return $result;
            }

            foreach($result['reviews'] as $review)
            {
                yield new OzonReviewDTO($review);
            }

            if(false === $result['has_next'])
            {
                break;
            }

            $this->lastId = $result['last_id'];
        }
    }
}
