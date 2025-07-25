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

use BaksDev\Ozon\Support\Type\OzonReviewProfileType;
use BaksDev\Support\Answer\Service\AutoMessagesReply;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * preview @see OzonReviewInfoDispatcher
 * next @see ReplyOzonReviewDispatcher
 */
#[AsMessageHandler]
final readonly class AutoReplyOzonReviewDispatcher
{
    public function __construct(
        #[Target('ozonSupportLogger')] private LoggerInterface $logger,
        private SupportHandler $SupportHandler,
        private CurrentSupportEventRepository $CurrentSupportEventRepository,
    ) {}

    /**
     * - добавляет сообщение с автоматическим ответом
     * - закрывает чат
     * - сохраняет новое состояние чата в БД
     */
    public function __invoke(AutoReplyOzonReviewMessage $message): void
    {
        $supportDTO = new SupportDTO();

        $CurrentSupportEvent = $this->CurrentSupportEventRepository
            ->forSupport($message->getId())
            ->find();

        if(false === ($CurrentSupportEvent instanceof SupportEvent))
        {
            $this->logger->critical(
                'ozon-support: Ошибка получения события по идентификатору :'.$message->getId(),
                [self::class.':'.__LINE__],
            );

            return;
        }

        // гидрируем DTO активным событием
        $CurrentSupportEvent->getDto($supportDTO);

        // обрабатываем только на открытый тикет
        if(false === ($supportDTO->getStatus()->getSupportStatus() instanceof SupportStatusOpen))
        {
            return;
        }

        $supportInvariableDTO = $supportDTO->getInvariable();

        if(false === ($supportInvariableDTO instanceof SupportInvariableDTO))
        {
            return;
        }

        // проверяем тип профиля у чата
        $TypeProfileUid = $supportInvariableDTO->getType();

        if(false === $TypeProfileUid->equals(OzonReviewProfileType::TYPE))
        {
            return;
        }

        // формируем сообщение в зависимости от условий отзыва
        $reviewRating = $message->getRating();

        /**
         * Текст сообщения в зависимости от рейтинга
         * по умолчанию текс с высоким рейтингом, 5 «HIGH»
         */

        $AutoMessagesReply = new AutoMessagesReply();
        $answerMessage = $AutoMessagesReply->high();

        if($reviewRating === 4 || $reviewRating === 3)
        {
            $answerMessage = $AutoMessagesReply->avg();
        }

        if($reviewRating < 3)
        {
            $answerMessage = $AutoMessagesReply->low();
        }

        /** Отправляем сообщение клиенту */

        $supportMessageDTO = new SupportMessageDTO()
            ->setName('admin (OZON Seller)')
            ->setMessage($answerMessage)
            ->setDate(new DateTimeImmutable('now'))
            ->setOutMessage();

        $supportDTO
            ->setStatus(new SupportStatus(SupportStatusClose::PARAM)) // закрываем чат
            ->addMessage($supportMessageDTO) // добавляем сформированное сообщение
        ;

        // сохраняем ответ
        $Support = $this->SupportHandler->handle($supportDTO);

        if(false === ($Support instanceof Support))
        {
            $this->logger->critical(
                'ozon-support: Ошибка при отправке автоматического ответа на отзыв',
                [$Support, self::class.':'.__LINE__]
            );
        }
    }
}
