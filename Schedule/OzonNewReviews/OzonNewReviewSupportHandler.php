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

namespace BaksDev\Ozon\Support\Schedule\OzonNewReviews;

use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Repository\AllProfileToken\AllProfileOzonTokenInterface;
use BaksDev\Ozon\Support\Messenger\Schedules\GetOzonReviewList\OzonReviewListMessage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Запускаем получение отзывов из Ozon
 * next @see OzonReviewListDispatcher
 */
#[AsMessageHandler]
final readonly class OzonNewReviewSupportHandler
{
    public function __construct(
        #[Target('ozonSupportLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private AllProfileOzonTokenInterface $allProfileOzonToken,
    ) {}

    /** Инициируем получение отзывов */
    public function __invoke(OzonNewReviewSupportMessage $message): void
    {
        /** Идентификаторы профилей пользователей, у которых есть активный токен Ozon */
        $profiles = $this->allProfileOzonToken
            ->onlyActiveToken()
            ->findAll();

        if(false === $profiles || false === $profiles->valid())
        {
            $this->logger->warning(
                'Профили с активными токенами Ozon не найдены',
                [self::class.':'.__LINE__],
            );

            return;
        }

        $this->logger->info(
            'Получаем по расписанию новые отзывы Ozon',
            [self::class.':'.__LINE__],
        );

        /** @var UserProfileUid $profile */
        foreach($profiles as $profile)
        {
            $this->messageDispatch->dispatch(
                message: new OzonReviewListMessage($profile),
                stamps: [new MessageDelay('5 seconds')],
                transport: (string) $profile,
            );
        }
    }
}
