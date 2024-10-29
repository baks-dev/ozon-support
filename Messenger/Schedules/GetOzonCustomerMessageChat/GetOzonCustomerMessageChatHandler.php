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

namespace BaksDev\Ozon\Support\Messenger\Schedules\GetOzonCustomerMessageChat;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDelay;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Support\Api\Chat\Get\History\GetOzonChatHistoryRequest;
use BaksDev\Ozon\Support\Api\Message\OzonMessageChatDTO;
use BaksDev\Ozon\Support\Repository\CurrentSupportByOzonChat\CurrentSupportByOzonChatRepository;
use BaksDev\Ozon\Support\Repository\FindExistMessageChat\FindExistMessageChatRepository;
use BaksDev\Ozon\Support\Type\Domain\OzonSupportProfileType;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Type\Priority\SupportPriority;
use BaksDev\Support\Type\Priority\SupportPriority\Collection\SupportPriorityHeight;
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

/**
 * // @TODO добавить описание
 */
#[AsMessageHandler]
final class GetOzonCustomerMessageChatHandler
{
    private LoggerInterface $logger;

    private bool $isAddMessage = false;

    public function __construct(
        LoggerInterface $ozonSupport,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private GetOzonChatHistoryRequest $chatHistoryRequest,
        private CurrentSupportByOzonChatRepository $supportByOzonChat,
        private FindExistMessageChatRepository $messageExist,
        private SupportHandler $supportHandler,
    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(GetOzonCustomerMessageChatMessage $message): void
    {
        //  для отслеживания созданных сообщения в чате
        $this->deduplicator
            ->namespace('ozon-support')
            ->expiresAfter(DateInterval::createFromDateString('1 minute')); // @TODO на сколько временя сохранять?

        $ticket = $message->getChatId();
        $profile = $message->getProfile();

        /** DTO для SupportEvent */
        $supportDTO = new SupportDTO();

        $supportDTO->setPriority(new SupportPriority(SupportPriorityHeight::PARAM)); // CustomerMessage - высокий приоритет
        $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM)); // Для нового сообщения - StatusOpen

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
            ->limit(1000)
            ->getMessages();

        if(false === $messagesChat)
        {
            $this->logger->warning(
                'Повтор выполнения через 1 час',
                [__FILE__.':'.__LINE__],
            );

            $this->messageDispatch
                ->dispatch(
                    message: $message,
                    // задержка 1 час для повторного запроса на получение сообщений чата
                    stamps: [new MessageDelay(DateInterval::createFromDateString('1 hour'))],
                    transport: (string) $profile,
                );

            return;
        }

        /**
         * Фильтруем сообщения:
         * - непрочитанные;
         * - кроме type seller;
         */
        $messagesChat = array_filter(iterator_to_array($messagesChat), function(OzonMessageChatDTO $message) {
            return false === $message->isRead() && $message->getUserType() !== 'Seller';
        });

        if(empty($messagesChat))
        {
            $this->logger->info(
                'Нет непрочитанных сообщений',
                [__FILE__.':'.__LINE__],
            );

            return;
        }

        // текущее событие чата по идентификатору чата (тикета) из Ozon
        $support = $this->supportByOzonChat->find($ticket);

        if($support)
        {
            /** Пересохраняю событие с новыми данными */
            $support->getDto($supportDTO);
        }

        $title = null;

        // устанавливаем заголовок чата
        if(null === $supportDTO->getInvariable()?->getTitle())
        {
            /** @var OzonMessageChatDTO $firstMessage */
            $firstMessage = end($messagesChat);

            $data = current($firstMessage->getData());

            preg_match('/(["\'])(.*?)\1/', $data, $quotesMatches);

            $article = strstr($data, 'артикул', false);

            if(is_string($article))
            {
                $title = $article;
            }

            if(false === empty($quotesMatches))
            {
                $title = $quotesMatches[0];
            }
        }

        if(null === $title)
        {
            $title = 'OZON';
        }

        // устанавливаем результат
        $supportInvariableDTO->setTitle($title);
        // добавляем в
        $supportDTO->setInvariable($supportInvariableDTO);

        /** @var OzonMessageChatDTO $chatMessage */
        foreach($messagesChat as $chatMessage)
        {
            // уникальный ключ сообщения для его проверки существования в текущем чате по данным о сообщении из Ozon
            $this->deduplicator->deduplication(
                [
                    $ticket,
                    $chatMessage->getId(),
                    $chatMessage->getUser(),
                    $profile,
                    self::class,
                ]
            );

            // проверка в дедубликаторе
            if($this->deduplicator->isExecuted())
            {
                $this->logger->warning(
                    'from deduplicator: сообщение уже добавлено в чат:'.$supportDTO->getEvent(),
                    [__FILE__.':'.__LINE__],
                );

                continue;
            }

            // проверка в БД
            $messageIsExist = $this->messageExist
                ->message($chatMessage->getId())
                ->isExist();

            if(true === $messageIsExist)
            {
                $this->logger->warning(
                    'from repository: сообщение уже добавлено в чат:'.$supportDTO->getEvent(),
                    [__FILE__.':'.__LINE__],
                );

                continue;
            }

            // подготовка DTO для нового сообщения
            $supportMessageDTO = new SupportMessageDTO();
            $supportMessageDTO->setName($chatMessage->getUser());
            $supportMessageDTO->setMessage(current($chatMessage->getData())); // @TODO разобраться с данными из массива data
            //            $supportMessageDTO->setDate($chatMessage->getCreated()); // @TODO пока не реализованно

            // уникальный идентификатор сообщения в Озон
            $supportMessageDTO->setExternal($chatMessage->getId());

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
                    sprintf(
                        'ozon-support: Ошибка %s при создании/обновлении чата поддержки:
                         Profile: %s | SupportEvent ID: %s | SupportEventInvariable.Ticker ID: %s',
                        $result,
                        (string) $profile,
                        $supportDTO->getEvent(),
                        $supportDTO->getInvariable()->getTicket(),
                    ),
                    [__FILE__.':'.__LINE__],
                );
            }
        }
    }
}

