<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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
use BaksDev\Ozon\Support\Api\Get\ChatMessages\GetOzonChatMessagesRequest;
use BaksDev\Ozon\Support\Api\Get\ChatMessages\OzonMessageChatDTO;
use BaksDev\Ozon\Support\Type\OzonSupportProfileType;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Entity\Support;
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
use DateInterval;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetOzonCustomerMessageChatHandler
{
    private LoggerInterface $logger;

    private bool $isAddMessage = false;

    public function __construct(
        LoggerInterface $ozonSupport,
        private readonly DeduplicatorInterface $deduplicator,
        private readonly GetOzonChatMessagesRequest $chatHistoryRequest,
        private readonly CurrentSupportEventByTicketInterface $supportByOzonChat,
        private readonly SupportHandler $supportHandler,
    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(GetOzonCustomerMessageChatMessage $message): void
    {
        //  для отслеживания созданных сообщения в чате
        $this->deduplicator
            ->namespace('ozon-support')
            ->expiresAfter(DateInterval::createFromDateString('1 day'));

        $ticket = $message->getChatId();
        $profile = $message->getProfile();

        /** DTO для SupportEvent */
        $supportDTO = new SupportDTO();

        $supportDTO->setPriority(new SupportPriority(SupportPriorityLow::class)); // CustomerMessage - высокий приоритет
        $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::class)); // Для нового сообщения - StatusOpen

        /** DTO для SupportInvariable */
        $supportInvariableDTO = new SupportInvariableDTO();
        $supportInvariableDTO->setProfile($profile);

        if(false === class_exists(OzonSupportProfileType::class))
        {
            $this->logger->critical(
                'Не добавлен тип профиля Ozon Support. Добавьте OzonSupportProfileType запустив соответствую команду',
                [__FILE__.':'.__LINE__],
            );

            return;
        }

        $supportInvariableDTO->setType(new TypeProfileUid(OzonSupportProfileType::TYPE));
        $supportInvariableDTO->setTicket($message->getChatId());

        /** Сообщения чата */
        // получаем массив сообщений из чата
        $messagesChat = $this->chatHistoryRequest
            ->profile($profile)
            ->chatId($ticket)
            ->sortByNew()
            ->limit(50)
            ->findAll();

        if(false === $messagesChat)
        {
            return;
        }

        if(false === $messagesChat->valid())
        {
            return;
        }

        $messagesChat = iterator_to_array($messagesChat);

        // текущее событие чата по идентификатору чата (тикета) из Ozon
        $support = $this->supportByOzonChat
            ->forTicket($ticket)
            ->find();

        if($support instanceof SupportEvent)
        {
            /** Пересохраняю событие с новыми данными */
            $support->getDto($supportDTO);
        }

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

            if(null !== $refund)
            {
                $title = 'Возврат № '.$refund;
            }

            // если ничего не найдено - по умолчанию
            if(null === $title)
            {
                $title = 'OZON';
            }

            $supportInvariableDTO->setTitle($title);
        }

        $supportDTO->setInvariable($supportInvariableDTO);

        /** @var OzonMessageChatDTO $chatMessage */
        foreach($messagesChat as $chatMessage)
        {
            // уникальный ключ сообщения для его проверки существования в текущем чате по данным о сообщении из Ozon
            $this->deduplicator->deduplication(
                [
                    $chatMessage,
                    self::class,
                ]
            );

            // проверка в дедубликаторе
            if($this->deduplicator->isExecuted())
            {
                continue;
            }

            // подготовка DTO для нового сообщения
            $supportMessageDTO = new SupportMessageDTO();
            $supportMessageDTO->setMessage($chatMessage->getData());
            $supportMessageDTO->setDate($chatMessage->getCreated());

            // уникальный идентификатор сообщения в Озон
            $supportMessageDTO->setExternal($chatMessage->getId());
            // если сообщение не от нас - помечаем как внутреннее

            // параметры в зависимости от типа юзера сообщения
            if($chatMessage->getUserType() === 'Seller')
            {
                $supportMessageDTO->setName('admin (OZON Seller)');
                $supportMessageDTO->setOutMessage();
            }

            if($chatMessage->getUserType() === 'Customer')
            {
                $supportMessageDTO->setName(sprintf('Пользователь (%s)', $chatMessage->getUserId()));
                $supportMessageDTO->setInMessage();
            }

            // Если не возможно определить тип - присваиваем идентификатор чата в качестве имени
            if($chatMessage->getUserType() !== 'Customer' && $chatMessage->getUserType() !== 'Seller')
            {
                $supportMessageDTO->setName($chatMessage->getUserId());
                $supportMessageDTO->setInMessage();
            }

            $supportDTO->addMessage($supportMessageDTO);

            // при добавлении нового сообщения открываем чат заново
            $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));

            $this->isAddMessage = true;
            $this->deduplicator->save();
        }

        if(true === $this->isAddMessage)
        {
            $result = $this->supportHandler->handle($supportDTO);

            if(false === $result instanceof Support)
            {
                $this->logger->critical(
                    sprintf('ozon-support: Ошибка %s при создании/обновлении чата поддержки', $result),
                    [
                        __FILE__.':'.__LINE__,
                        $profile,
                        $supportDTO->getInvariable()?->getTicket(),
                    ],
                );
            }
        }
    }
}

