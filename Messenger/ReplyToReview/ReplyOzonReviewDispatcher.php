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

namespace BaksDev\Ozon\Support\Messenger\ReplyToReview;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Support\Api\ReviewComments\Post\PostOzonReviewCommentRequest;
use BaksDev\Ozon\Support\Type\OzonReviewProfileType;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReplyOzonReviewDispatcher
{
    public function __construct(
        #[Target('ozonSupportLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private PostOzonReviewCommentRequest $postCommentRequest,
        private CurrentSupportEventRepository $currentSupportEvent,
    ) {}

    /**
     * - при закрытии чата с отзывом - отсылает ответ (комментарий) на отзыв
     * - изменяет статус отзыва на "обработанный"
     */
    public function __invoke(SupportMessage $message): void
    {
        $CurrentSupportEvent = $this->currentSupportEvent
            ->forSupport($message->getId())
            ->find();

        if(false === ($CurrentSupportEvent instanceof SupportEvent))
        {
            $this->logger->critical(
                'Ошибка получения события по идентификатору :'.$message->getId(),
                [self::class.':'.__LINE__],
            );

            return;
        }


        $UserProfileUid = $CurrentSupportEvent->getInvariable()?->getProfile();

        if(false === ($UserProfileUid instanceof UserProfileUid))
        {
            $this->logger->critical(
                sprintf('ozon-support: Ошибка получения профиля по идентификатору : %s', $message->getId()));

            return;

        }

        $supportDTO = new SupportDTO();

        // гидрируем DTO активным событием
        $CurrentSupportEvent->getDto($supportDTO);

        // обрабатываем только закрытые тикеты
        if(false === ($supportDTO->getStatus()->getSupportStatus() instanceof SupportStatusClose))
        {
            return;
        }

        $SupportInvariableDTO = $supportDTO->getInvariable();

        if(false === ($SupportInvariableDTO instanceof SupportInvariableDTO))
        {
            return;
        }

        // проверяем тип профиля у чата
        $TypeProfileUid = $SupportInvariableDTO->getType();

        if(false === $TypeProfileUid->equals(OzonReviewProfileType::TYPE))
        {
            return;
        }

        // последнее сообщение в закрытом чате = наш ответ
        /** @var SupportMessageDTO $lastMessage */
        $lastMessage = $supportDTO->getMessages()->last();

        // проверяем наличие внешнего ID - для наших ответов его быть не должно
        if(null !== $lastMessage->getExternal())
        {
            return;
        }

        /** Отправляем ответ на отзыв и меняем его статус на "обработанный" */
        $request = $this->postCommentRequest
            ->forTokenIdentifier($UserProfileUid)
            ->reviewId($SupportInvariableDTO->getTicket())
            ->text($lastMessage->getMessage())
            ->markAsProcessed()
            ->postReviewComment();

        if(false === $request)
        {
            $this->logger->warning(
                sprintf('Повтор отправки ответа на отзыв %s через 1 минут', $SupportInvariableDTO->getTicket()),
                [self::class.':'.__LINE__],
            );

            $this->messageDispatch
                ->dispatch(
                    message: $message,
                    stamps: [new MessageDelay('1 minute')],
                    transport: 'ozon-support-low',
                );
        }
    }
}
