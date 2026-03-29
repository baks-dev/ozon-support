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

namespace BaksDev\Ozon\Support\Messenger\OzonReview;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Products\Api\Card\Identifier\GetOzonCardOfferIdRequest;
use BaksDev\Ozon\Repository\OzonTokensByProfile\OzonTokensByProfileInterface;
use BaksDev\Ozon\Support\Api\ReviewList\Get\OzonReviewDTO;
use BaksDev\Ozon\Support\Api\ReviewList\Get\OzonReviewListRequest;
use BaksDev\Ozon\Support\Messenger\Schedules\GetOzonReviewList\OzonReviewListMessage;
use BaksDev\Products\Review\Repository\FindExistByExternal\FindExistByExternalInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Подготовка данных для product reviews на основе Ozon Reviews
 */
#[Autoconfigure(shared: false)]
#[AsMessageHandler]
final readonly class OzonReviewHandler
{

    public function __construct(
        private OzonReviewListRequest $reviewListRequest,
        private OzonTokensByProfileInterface $OzonTokensByProfile,
        private GetOzonCardOfferIdRequest $getOzonCardOfferIdRequest,
        private DeduplicatorInterface $deduplicator,
        private FindExistByExternalInterface $existByExternal,
        private MessageDispatchInterface $messageDispatch,
    ) {}

    public function __invoke(OzonReviewListMessage $message): void
    {

        $isExecuted = $this
            ->deduplicator
            ->expiresAfter('1 minute')
            ->deduplication([$message->getProfile(), self::class]);

        if($isExecuted->isExecuted())
        {
            return;
        }

        $isExecuted->save();


        /** Получаем все токены профиля */

        $tokensByProfile = $this->OzonTokensByProfile
            ->forProfile($message->getProfile())
            ->onlyCardUpdate()
            ->findAll();

        if(false === $tokensByProfile || false === $tokensByProfile->valid())
        {
            return;
        }

        foreach($tokensByProfile as $OzonTokenUid)
        {

            /**
             * Получить список отзывов:
             * - без ответа (UNPROCESSED)
             * - от новых к старым
             */
            $reviews = $this->reviewListRequest
                ->forTokenIdentifier($OzonTokenUid)
                ->status(OzonReviewListRequest::STATUS_UNPROCESSED)
                ->sort(OzonReviewListRequest::SORT_DESC)
                ->getReviewList();


            /* При отсутствии необработанных отзывов - прерывать работу */
            if(false === $reviews->valid())
            {
                continue;
            }

            /* Итерируемся по полученным отзывам */
            /** @var OzonReviewDTO $review */
            foreach($reviews as $review)
            {

                /* Проверка на существование отзыва по внешнему Id */

                $reviewExists = $this->existByExternal
                    ->external($review->getId())
                    ->exist();

                if(true === $reviewExists)
                {
                    continue;
                }


                /* Идентификатор товара в Ozon, соответствует article товара */
                $article = $this->getOzonCardOfferIdRequest
                    ->forTokenIdentifier($OzonTokenUid)
                    ->sku($review->getSku())
                    ->find();


                if(false === $article)
                {
                    continue;
                }


                /** Сообщение/Текст отзыва */

                /* Отзыв создается только, если в ozon отзыве есть текст сообщения */
                if(true === empty($review->getText()))
                {
                    continue;
                }


                /* Создать Message */

                $OzonReviewMessage = new OzonReviewMessage(
                    article: $article,
                    rating: $review->getRating(),
                    text: $review->getText(),
                    token: $OzonTokenUid->getValue(),
                    external: $review->getId(),
                    profile: $message->getProfile()
                );


                $this->messageDispatch->dispatch(
                    message: $OzonReviewMessage,
                    stamps: [new MessageDelay(sprintf('%s seconds', 1))],
                    transport: 'ozon-support-low',
                );

            }

        }

    }

}