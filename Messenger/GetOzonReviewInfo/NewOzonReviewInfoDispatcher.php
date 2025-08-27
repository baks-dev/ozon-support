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

namespace BaksDev\Ozon\Support\Messenger\GetOzonReviewInfo;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Repository\OzonTokensByProfile\OzonTokensByProfileInterface;
use BaksDev\Ozon\Support\Api\ReviewInfo\Get\GetOzonReviewInfoRequest;
use BaksDev\Ozon\Support\Api\ReviewInfo\Get\OzonReviewInfoDTO;
use BaksDev\Ozon\Support\Messenger\ReplyToReview\AutoReplyOzonReviewMessage;
use BaksDev\Ozon\Support\Type\OzonReviewProfileType;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Repository\ExistTicket\ExistSupportTicketInterface;
use BaksDev\Support\Type\Priority\SupportPriority;
use BaksDev\Support\Type\Priority\SupportPriority\Collection\SupportPriorityLow;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * preview @see OzonReviewListDispatcher
 * next @see AutoReplyOzonReviewDispatcher
 */
#[AsMessageHandler]
final readonly class NewOzonReviewInfoDispatcher
{
    public function __construct(
        #[Target('ozonSupportLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private SupportHandler $supportHandler,
        private ExistSupportTicketInterface $existSupportTicket,
        private GetOzonReviewInfoRequest $getOzonReviewInfoRequest
    )
    {
        $this->deduplicator
            ->namespace('ozon-support')
            ->expiresAfter(DateInterval::createFromDateString('1 day'));
    }

    /**
     * - получает информацию об отзыве от Ozon Seller Api
     * - подготавливает и сохраняет чат с отзывом и его сообщениями
     * - ПО УСЛОВИЯМ отправляет авто ответ на отзывы
     */
    public function __invoke(GetOzonReviewInfoMessage $message): void
    {
        $deduplicator = $this->deduplicator->deduplication([
            $message->getReviewId(),
            self::class,
        ]);

        if($deduplicator->isExecuted())
        {
            return;
        }

        // id тикета = id отзыва
        $reviewExist = $this->existSupportTicket
            ->ticket($message->getReviewId())
            ->exist();

        if(true === $reviewExist)
        {
            return;
        }

        /** @var OzonReviewInfoDTO $reviewInfo */
        $reviewInfo = $this->getOzonReviewInfoRequest
            ->forTokenIdentifier($message->getIdentifier())
            ->getReviewInfo($message->getReviewId());

        // при ошибке от Ozon API - повторяем запрос через 10 минут
        if(false === ($reviewInfo instanceof OzonReviewInfoDTO))
        {
            $this->messageDispatch->dispatch(
                $message,
                [new MessageDelay('10 minutes')],
                'ozon-support',
            );

            return;
        }

        /** SupportEvent */
        $SupportDTO = new SupportDTO() // done
        ->setPriority(new SupportPriority(SupportPriorityLow::class)) // Для отзывов - низкий приоритет
        ->setStatus(new SupportStatus(SupportStatusOpen::class)); // Для нового отзыва - StatusOpen

        /** Присваиваем токен для последующего поиска */
        $SupportDTO
            ->getToken()
            ->setValue($message->getIdentifier());

        /** SupportInvariable */
        $supportInvariableDTO = new SupportInvariableDTO()
            //->setProfile($message->getProfile())
            ->setType(new TypeProfileUid(OzonReviewProfileType::TYPE))
            ->setTicket($reviewInfo->getId())
            ->setTitle($reviewInfo->getTitle());

        $SupportDTO->setInvariable($supportInvariableDTO);

        /** SupportMessage */
        $supportMessageDTO = new SupportMessageDTO()
            ->setExternal($reviewInfo->getSku())
            ->setName('пользователь')
            ->setMessage($reviewInfo->getMessage()) // Текст отзыва (с текстом и вложениями)
            ->setDate($reviewInfo->getPublished())
            ->setInMessage();

        $SupportDTO->addMessage($supportMessageDTO);

        $result = $this->supportHandler->handle($SupportDTO);

        if(false === ($result instanceof Support))
        {
            $this->logger->critical(
                sprintf('avito-support: Ошибка %s при создании нового отзыва', $result),
                [self::class.':'.__LINE__],
            );

            return;
        }

        $deduplicator->save();


        // после добавления отзыва в БД - инициирую авто ответ по условию

        /**
         * Условия ответа на отзывы
         *
         * рейтинг равен 5 с текстом:
         * - авто комментарий с благодарностью (сообщение)
         *
         * рейтинг меньше 5 и без текста:
         * - авто комментарий с извинениями (сообщение)
         *
         * рейтинг меньше 5 с текстом:
         * - отвечает контент менеджер
         */

        if($reviewInfo->getRating() === 5 || empty($reviewInfo->getText()))
        {
            $this->messageDispatch->dispatch(
                message: new AutoReplyOzonReviewMessage($result->getId(), $reviewInfo->getRating()),
                transport: 'ozon-support',
            );
        }

        $deduplicator->save();

    }
}
