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
use BaksDev\Support\Repository\SupportCurrentEvent\CurrentSupportEventRepository;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * preview @see OzonReviewInfoHandler
 * next @see ReplyOzonReviewHandler
 */
#[AsMessageHandler]
final readonly class AutoReplyOzonReviewHandler
{
    public function __construct(
        #[Target('ozonSupportLogger')] private LoggerInterface $logger,
        private SupportHandler $supportHandler,
        private CurrentSupportEventRepository $currentSupportEvent,
    ) {}

    /**
     * - добавляет сообщение с автоматическим ответом
     * - закрывает чат
     * - сохраняет новое состояние чата в БД
     */
    public function __invoke(AutoReplyOzonReviewMessage $message): void
    {
        $supportDTO = new SupportDTO();

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

        // гидрируем DTO активным событием
        $supportEvent->getDto($supportDTO);

        // обрабатываем только на открытый тикет
        if(false === ($supportDTO->getStatus()->getSupportStatus() instanceof SupportStatusOpen))
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

        // формируем сообщение в зависимости от условий отзыва
        $reviewRating = $message->getRating();

        if($reviewRating === 5)
        {
            $answerMessage = '<div>Здравствуйте! 
        Благодарим Вас за то, что выбрали наш магазин для покупки! 
        Мы ценим Ваше доверие и всегда стремимся предоставить лучший сервис.</div>';
        }
        else
        {
            $answerMessage = '<div>Здравствуйте! 
        Приносим извинения за доставленные неудобства! 
        Мы ценим Ваше доверие и всегда стремимся предоставить лучший сервис.</div>';
        }

        $supportMessageDTO = new SupportMessageDTO();

        $supportMessageDTO->setName('admin (OZON Seller)');
        $supportMessageDTO->setMessage($answerMessage);
        $supportMessageDTO->setDate(new \DateTimeImmutable('now'));
        $supportMessageDTO->setOutMessage();

        // добавляем сформированное сообщение
        $supportDTO->addMessage($supportMessageDTO);

        // закрываем чат
        $supportDTO->setStatus(new SupportStatus(SupportStatusClose::PARAM));

        // сохраняем ответ
        $this->supportHandler->handle($supportDTO);
    }
}
