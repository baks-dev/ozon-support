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

namespace BaksDev\Ozon\Support\Schedule\FindProfileForCreateOzonSupport\Tests;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Ozon\Support\Api\Chat\Get\History\GetOzonChatHistoryRequest;
use BaksDev\Ozon\Support\Api\Chat\Get\List\GetOzonChatListRequest;
use BaksDev\Ozon\Support\Api\Chat\OzonChatDTO;
use BaksDev\Ozon\Support\Api\Message\OzonMessageChatDTO;
use BaksDev\Ozon\Support\Messenger\Schedules\GetOzonCustomerMessageChat\GetOzonCustomerMessageChatMessage;
use BaksDev\Ozon\Support\Repository\CurrentSupportByOzonChat\CurrentSupportByOzonChatRepository;
use BaksDev\Ozon\Support\Type\Domain\OzonSupportProfileType;
use BaksDev\Ozon\Type\Authorization\OzonAuthorizationToken;
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
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateInterval;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group ozon-support
 */
#[When(env: 'test')]
class OzonSupportTest extends KernelTestCase
{
    private static bool $isAddMessage = false;

    private static OzonAuthorizationToken $authorization;

    public static function setUpBeforeClass(): void
    {
        self::$authorization = new OzonAuthorizationToken(
            new UserProfileUid(),
            $_SERVER['TEST_OZON_TOKEN'],
            $_SERVER['TEST_OZON_CLIENT'],
            $_SERVER['TEST_OZON_WAREHOUSE']
        );
    }

    public function testGetOzonChatListHandler(): GetOzonCustomerMessageChatMessage
    {
        /** @var GetOzonChatListRequest $ozonChatListRequest */
        $ozonChatListRequest = self::getContainer()->get(GetOzonChatListRequest::class);
        $ozonChatListRequest->TokenHttpClient(self::$authorization);

        // получаем массив чатов: открытые и с непрочитанными сообщениями
        $listChats = $ozonChatListRequest
            ->opened()
            ->getListChats();

        if(false === $listChats)
        {
            self::addWarning('List Chats not found');
        }

        self::assertNotFalse($listChats);

        // только чаты с покупателями
        $customerChats = array_filter(iterator_to_array($listChats), function(OzonChatDTO $chat) {
            return $chat->getType() === 'Buyer_Seller';
        });

        if(empty($listChats))
        {
            self::addWarning('Buyer_Seller not found');
        }

        //        dd($customerChats);
        //        $chatId = current($customerChats)->getId();
        $chatId = '41e5c5f2-804a-491a-837c-150481a9c353';

        return new GetOzonCustomerMessageChatMessage($chatId, self::$authorization->getProfile());
    }

    /**
     * @depends testGetOzonChatListHandler
     */
    public function testGetOzonCustomerMessageChatHandler(GetOzonCustomerMessageChatMessage $message): void
    {
        $container = self::getContainer();

        /** @var DeduplicatorInterface $deduplicator */
        $deduplicator = $container->get(DeduplicatorInterface::class);

        //  для отслеживания созданных сообщения в чате
        $deduplicator
            ->namespace('ozon-support')
            ->expiresAfter(DateInterval::createFromDateString('1 minute'));

        /** @var GetOzonChatHistoryRequest $chatHistoryRequest */
        $chatHistoryRequest = $container->get(GetOzonChatHistoryRequest::class);
        $chatHistoryRequest->TokenHttpClient(self::$authorization);

        /** @var CurrentSupportByOzonChatRepository $supportByOzonChat */
        $supportByOzonChat = $container->get(CurrentSupportByOzonChatRepository::class);

        /** @var SupportHandler $supportHandler */
        $supportHandler = $container->get(SupportHandler::class);

        $ticket = $message->getChatId();
        $profile = $message->getProfile();

        /** Подготавливаю DTO для события */
        $supportDTO = new SupportDTO();
        $supportDTO->setPriority(new SupportPriority(SupportPriorityHeight::PARAM)); // Customer - высокий приоритет
        $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM)); // Для нового сообщения - open

        $supportInvariableDTO = new SupportInvariableDTO();
        $supportInvariableDTO->setProfile($profile);

        if(false === class_exists(OzonSupportProfileType::class))
        {
            self::addWarning('OzonSupportProfileType not found');
            self::assertTrue(false);
        }

        $supportInvariableDTO->setType(new TypeProfileUid(OzonSupportProfileType::TYPE)); // @TODO ПЕРЕДЕЛАТЬ - добавить тип для Озон

        // уникальный внешний идентификатор чата - в тесте генерируем
        $ticketId = uniqid('test_');
        $supportInvariableDTO->setTicket($ticketId);

        // получаем массив сообщений из чата
        $messagesChat = $chatHistoryRequest
            ->chatId($ticket)
            ->sortByNew()
            ->limit(1000)
            ->getMessages();

        /**
         * Фильтруем сообщения:
         * - только непрочитанные;
         * - кроме type seller;
         */
        $messagesChat = array_filter(iterator_to_array($messagesChat), function(OzonMessageChatDTO $message) {
            return $message->getUserType() !== 'Seller'; //&& return false === $message->isRead();
        });


        // текущее событие чата по идентификатору чата (тикета) из Ozon
        $support = $supportByOzonChat->find($ticket);

        if($support)
        {
            // пересохраняю событие с новыми данными
            $support->getDto($supportDTO);
        }

        $title = null;

        // устанавливаем заголовок чата
        if(null === $supportDTO->getInvariable())
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

            if(null === $title)
            {
                $title = 'OZON';
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
            $deduplicator->deduplication(
                [
                    $ticket,
                    $chatMessage->getId(),
                    $chatMessage->getUser(),
                    $profile,
                    self::class,
                ]
            );

            if($deduplicator->isExecuted())
            {
                self::addWarning('Сообщение уже добавлено');

                continue;
            }

            // Формируем сообщение
            $supportMessageDTO = new SupportMessageDTO();
            $supportMessageDTO->setName($chatMessage->getUser());
            $supportMessageDTO->setMessage(current($chatMessage->getData()));
            //            $supportMessageDTO->setDate($chatMessage->getCreated()); // @TODO пока не реализованно

            // уникальный идентификатор сообщения в Озон
            $supportMessageDTO->setExternal($chatMessage->getId());

            $supportDTO->addMessage($supportMessageDTO);

            // при добавлении нового сообщения открываем чат заново
            $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));

            self::$isAddMessage = true;
            $deduplicator->save();
        }

        if(true === self::$isAddMessage)
        {
            $result = $supportHandler->handle($supportDTO);

            self::assertTrue($result instanceof Support);

            self::addWarning('Чат добавлен/обновлен');
        }
    }
}
