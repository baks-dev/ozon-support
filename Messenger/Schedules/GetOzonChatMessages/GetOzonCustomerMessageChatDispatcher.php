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

namespace BaksDev\Ozon\Support\Messenger\Schedules\GetOzonChatMessages;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Orders\Order\Repository\SearchProfileByNumber\SearchProfileByNumberInterface;
use BaksDev\Ozon\Support\Api\Get\ChatMessages\GetOzonChatMessagesRequest;
use BaksDev\Ozon\Support\Api\Get\ChatMessages\OzonMessageChatDTO;
use BaksDev\Ozon\Support\Type\OzonSupportProfileType;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Repository\ExistTicket\ExistSupportTicketInterface;
use BaksDev\Support\Repository\SupportCurrentEventByTicket\CurrentSupportEventByTicketInterface;
use BaksDev\Support\Type\Priority\SupportPriority;
use BaksDev\Support\Type\Priority\SupportPriority\Collection\SupportPriorityLow;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Получает новые сообщения Ozon
 */
#[AsMessageHandler]
final class GetOzonCustomerMessageChatDispatcher
{
    private bool $isAddMessage = false;

    public function __construct(
        #[Target('ozonSupportLogger')] private readonly LoggerInterface $logger,
        private readonly DeduplicatorInterface $deduplicator,
        private readonly GetOzonChatMessagesRequest $chatHistoryRequest,
        private readonly CurrentSupportEventByTicketInterface $supportByOzonChat,
        private readonly ExistSupportTicketInterface $existSupportTicket,
        private readonly SupportHandler $supportHandler,
        private readonly SearchProfileByNumberInterface $SearchProfileByNumberRepository,
    )
    {
        $this->deduplicator
            ->namespace('ozon-support')
            ->expiresAfter(DateInterval::createFromDateString('1 day'));
    }

    public function __invoke(GetOzonCustomerMessageChatMessage $message): void
    {
        $ticket = $message->getChatId();

        /** SupportEvent */
        $SupportDTO = new SupportDTO() // done
        ->setPriority(new SupportPriority(SupportPriorityLow::class)) // CustomerMessage - высокий приоритет
        ->setStatus(new SupportStatus(SupportStatusOpen::class)); // Для нового сообщения - StatusOpen

        /** Присваиваем токен для последующего поиска */
        $SupportDTO->getToken()->setValue($message->getIdentifier());

        /** SupportInvariable */
        $supportInvariableDTO = new SupportInvariableDTO()
            //->setProfile($message->getProfile())
            ->setType(new TypeProfileUid(OzonSupportProfileType::TYPE))
            ->setTicket($message->getChatId());


        /** Сообщения чата */

        // получаем массив сообщений из чата
        $messagesChat = $this->chatHistoryRequest
            ->forTokenIdentifier($message->getIdentifier())
            ->chatId($ticket)
            ->sortByNew()
            ->limit(50)
            ->findAll();

        if(false === $messagesChat || false === $messagesChat->valid())
        {
            return;
        }

        /** @var array<int, OzonMessageChatDTO> $messagesChat */
        $messagesChat = iterator_to_array($messagesChat);

        // текущее событие чата по идентификатору чата (тикета) из Ozon
        $support = $this->supportByOzonChat
            ->forTicket($ticket)
            ->find();

        /** Пересохраняю событие с новыми данными */
        false === ($support instanceof SupportEvent) ?: $support->getDto($SupportDTO);


        /** Устанавливаем заголовок чата - выполнится только один раз при сохранении чата */
        if(false === $support)
        {
            $title = null;

            /**
             * @var OzonMessageChatDTO $firstMessage
             *
             * Самое старое сообщение в диалоге
             */
            $firstMessage = end($messagesChat);

            $messageText = $firstMessage->getData();

            // ищем артикул - подставляем в заголовок
            $article = strstr($messageText, 'артикул', false);

            if(is_string($article))
            {
                $title = $article;
            }

            // ищем текст в двойных кавычках - подставляем в заголовок
            preg_match('/"(.*?)"/', $messageText, $quotesMatches);

            if(false === empty($quotesMatches))
            {
                $title = str_replace('"', '', $quotesMatches[0]);
            }

            // определяем возврат - подставляем в заголовок
            $refund = $firstMessage->getRefundTitle();

            if(false === empty($refund))
            {
                $title = 'Возврат № '.$refund;
            }

            $supportInvariableDTO->setTitle($title);
        }

        /**
         * Если сообщение не адресовано профилю - пробуем найти в тексте идентификаторы заказа
         */

        $UserProfileUid = $SupportDTO->getInvariable()?->getProfile();

        /** Пробуем найти по заголовку */
        if(false === ($UserProfileUid instanceof UserProfileUid) && false === empty($title))
        {
            // Для формата с дефисами: ....XXXXXXXX-XXXX....
            if(preg_match('/\b\d{8,}-\d{4,}(?:-\d+)?\b/', $title, $matches))
            {
                /** Пробуем определить профиль по идентификатору заказа */
                $foundValue = $matches[0];

                // Удаляем конечные символы от 1 до 9
                $foundValue = preg_replace('/-[1-9](\d)?$/', '', $foundValue);

                $UserProfileUid = $this->SearchProfileByNumberRepository->find($foundValue);

                if($UserProfileUid instanceof UserProfileUid)
                {
                    $supportInvariableDTO->setProfile($UserProfileUid);
                    $supportInvariableDTO->setTitle(sprintf('Заказ #%s', $foundValue));
                }
            }
        }


        $UserProfileUid = $SupportDTO->getInvariable()?->getProfile();

        /** Пробуем найти идентификатор заказа по тексту */
        if(false === ($UserProfileUid instanceof UserProfileUid))
        {
            foreach($messagesChat as $search)
            {
                // Для формата с дефисами: ....XXXXXXXX-XXXX....
                if(preg_match('/\b\d{8,}-\d{4,}(?:-\d+)?\b/', $search->getData(), $matches))
                {
                    /** Пробуем определить профиль по идентификатору заказа */
                    $foundValue = $matches[0];

                    // Удаляем конечные символы от 1 до 9
                    $foundValue = preg_replace('/-[1-9](\d)?$/', '', $foundValue);

                    $UserProfileUid = $this->SearchProfileByNumberRepository->find($foundValue);

                    if($UserProfileUid instanceof UserProfileUid)
                    {
                        $supportInvariableDTO->setProfile($UserProfileUid);
                        $supportInvariableDTO->setTitle(sprintf('Заказ #%s', $foundValue));

                        break;
                    }
                }
            }
        }

        $SupportDTO->setInvariable($supportInvariableDTO);

        /** @var OzonMessageChatDTO $chatMessage */
        foreach($messagesChat as $chatMessage)
        {
            // уникальный ключ сообщения для его проверки существования в текущем чате по данным о сообщении из Ozon
            $deduplicator = $this->deduplicator->deduplication([
                $chatMessage,
                self::class,
            ]);

            // проверка в дедубликаторе
            if($deduplicator->isExecuted())
            {
                continue;
            }

            // проверка в базе
            $chatExist = $this->existSupportTicket
                ->ticket($chatMessage->getId())
                ->exist();

            if($chatExist)
            {
                continue;
            }

            // подготовка DTO для нового сообщения
            $supportMessageDTO = (new SupportMessageDTO())
                ->setMessage($chatMessage->getData())
                ->setDate($chatMessage->getCreated())
                ->setExternal($chatMessage->getId()); // идентификатор сообщения в Озон

            // параметры в зависимости от типа юзера сообщения
            if($chatMessage->getUserType() === 'Seller')
            {
                $supportMessageDTO
                    ->setName('admin (OZON Seller)')
                    ->setOutMessage();
            }

            if($chatMessage->getUserType() === 'Customer')
            {
                $supportMessageDTO
                    ->setName(sprintf('Пользователь (%s)', $chatMessage->getUserId()))
                    ->setInMessage();
            }

            // Если не возможно определить тип - присваиваем идентификатор чата в качестве имени
            if($chatMessage->getUserType() !== 'Customer' && $chatMessage->getUserType() !== 'Seller')
            {
                $supportMessageDTO
                    ->setName($chatMessage->getUserId())
                    ->setInMessage();
            }

            // при добавлении нового сообщения открываем чат заново
            $SupportDTO
                ->setStatus(new SupportStatus(SupportStatusOpen::class))
                ->addMessage($supportMessageDTO);

            $this->isAddMessage ?: $this->isAddMessage = true;

            $deduplicator->save();
        }

        /** Сохраняем, если имеются новые сообщения в массиве */
        if(true === $this->isAddMessage)
        {
            $handle = $this->supportHandler->handle($SupportDTO);

            if(false === $handle instanceof Support)
            {
                $this->logger->critical(
                    sprintf('ozon-support: Ошибка %s при создании/обновлении чата поддержки', $handle),
                    [
                        self::class.':'.__LINE__,
                        var_export($message, true),
                        $SupportDTO->getInvariable()?->getTicket(),
                    ],
                );
            }
        }
    }
}

