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

namespace BaksDev\Ozon\Support\Messenger\Schedules\GetOzonReviewList;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Support\Api\ReviewList\Get\OzonReviewListRequest;
use BaksDev\Ozon\Support\Messenger\GetOzonReviewInfo\GetOzonReviewInfoMessage;
use BaksDev\Support\Repository\ExistTicket\ExistSupportTicketInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * - получаем список отзывов
 * - на каждый отзыв кидаем сообщение для его обработки
 *
 * prev @see OzonNewReviewSupportHandler
 * next @see OzonReviewInfoDispatcher
 */
#[AsMessageHandler]
final readonly class OzonReviewListDispatcher
{

    public function __construct(
        private MessageDispatchInterface $messageDispatch,
        private OzonReviewListRequest $reviewListRequest,
    ) {}

    public function __invoke(OzonReviewListMessage $message): void
    {

        /**
         * Получаем список отзывов:
         * - без ответа (UNPROCESSED)
         * - от новых к старым
         */
        $reviewList = $this->reviewListRequest
            ->forTokenIdentifier($message->getProfile())
            ->status(OzonReviewListRequest::STATUS_UNPROCESSED)
            ->sort(OzonReviewListRequest::SORT_DESC)
            ->getReviewList();

        // при ошибке от Ozon API - повторяем запрос через 10 минут
        if(false === $reviewList)
        {
            $this->messageDispatch->dispatch(
                $message,
                [new MessageDelay('10 minutes')],
                'ozon-support',
            );
            return;
        }

        // при отсутствии необработанных отзывов - прерываем работу
        if(false === $reviewList->valid())
        {
            return;
        }

        // каждый отзыв - это наш чат
        foreach($reviewList as $review)
        {
            $this->messageDispatch->dispatch(
                message: new GetOzonReviewInfoMessage($message->getProfile(), $review->getId()),
                transport: 'ozon-support',
            );
        }
    }
}
