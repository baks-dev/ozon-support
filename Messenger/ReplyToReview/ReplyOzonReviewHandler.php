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
use BaksDev\Support\Messenger\SupportMessage;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReplyOzonReviewHandler
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
        $supportEvent = $this->currentSupportEvent
            ->forSupport($message->getId())
            ->find();

        if(false === $supportEvent)
        {
            $this->logger->critical(
                'Ошибка получения события по идентификатору :'.$message->getId(),
                [self::class.':'.__LINE__],
            );

            return;
        }

        $supportDTO = new SupportDTO();

        // гидрируем DTO активным событием
        $supportEvent->getDto($supportDTO);

        // обрабатываем только закрытые тикеты
        if(false === ($supportDTO->getStatus()->getSupportStatus() instanceof SupportStatusClose))
        {
            return;
        }

        $supportInvariableDTO = $supportDTO->getInvariable();

        if(is_null($supportInvariableDTO))
        {
            return;
        }

        // проверяем тип профиля у чата
        $supportProfileType = $supportInvariableDTO->getType();

        if(false === $supportProfileType->equals(OzonReviewProfileType::TYPE))
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
            ->profile($supportInvariableDTO->getProfile())
            ->reviewId($supportInvariableDTO->getTicket())
            ->text($lastMessage->getMessage())
            ->markAsProcessed()
            ->postReviewComment();

        if(false === $request)
        {
            $this->logger->warning(
                sprintf('Повтор отправки ответа на отзыв %s через 1 минут', $supportInvariableDTO->getTicket()),
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
