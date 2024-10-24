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

namespace BaksDev\Ozon\Support\Messenger\GetOzonChatList;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Support\Api\Chat\Get\History\GetOzonChatHistoryRequest;
use BaksDev\Ozon\Support\Api\Chat\Get\List\GetOzonChatListRequest;
use BaksDev\Ozon\Support\Api\Chat\OzonChatDTO;
use BaksDev\Ozon\Support\Messenger\GetOzonCustomerMessageChat\GetOzonCustomerMessageChatMessage;
use BaksDev\Ozon\Support\Repository\CurrentSupportByOzonChat\CurrentSupportByOzonChatRepository;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Получаем профиль пользователя с активным токеном на Ozon:
 *
 * - делаем запрос на получение списка чатов: открытые и с непрочитанными сообщениями
 * - фильтруем только чаты с покупателями
 * - получаем сообщения чата: от новых к старым
 * - добавляем новые сообщения в чат
 * - создаем/обновляем чат техподдержки (Support)
 */
#[AsMessageHandler]
final readonly class GetOzonChatListHandler
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $ozonSupport,
        private MessageDispatchInterface $messageDispatch,
        private DeduplicatorInterface $deduplicator,
        private GetOzonChatListRequest $ozonChatListRequest,
        private GetOzonChatHistoryRequest $chatHistoryRequest,
        private CurrentSupportByOzonChatRepository $currentSupport,
        private SupportHandler $supportHandler,
    )
    {
        $this->logger = $ozonSupport;
    }

    public function __invoke(GetOzonChatListMessage $message): void
    {
        // для отслеживания созданных сообщения в чате
        //        $deduplicator = $this->deduplicator
        //            ->namespace('ozon-support')
        //            ->expiresAfter(DateInterval::createFromDateString('1 day'));

        $profile = $message->getProfile();

        // получаем массив чатов: открытые и с непрочитанными сообщениями
        $chats = $this->ozonChatListRequest
            ->profile($profile)
            ->opened()
            ->unreadMessageOnly()
            ->getChatList();

        // @TODO где бросать исключение?
        if(false === $chats->valid())
        {
            $this->logger->warning(
                '',
                [__FILE__.':'.__LINE__],
            );

            return;
        }

        // только чаты с покупателями
        $customerChats = array_filter(iterator_to_array($chats), function(OzonChatDTO $chat) {
            return $chat->getType() === 'Buyer_Seller';
        });

        /** @var OzonChatDTO $customerChat */
        foreach($customerChats as $customerChat)
        {
            $this->messageDispatch->dispatch(
                message: new GetOzonCustomerMessageChatMessage($customerChat->getId(), $profile),
                transport: (string) $profile,
            );
        }

        //         @TODO уточнить
        //        /** @var OzonChatDTO $customerChat */
        //        foreach($customerChats as $customerChat)
        //        {
        //            /** Чат */
        //            $supportDTO = new SupportDTO();
        //
        //            $support = $this->currentSupport->find($customerChat->getId());
        //
        //            if($support)
        //            {
        //                // пересохраняю событие
        //                $support->getDto($supportDTO);
        //            }
        //            else
        //            {
        //                // создаю событие
        //                // Customer - высокий приоритет
        //                $supportDTO->setPriority(new SupportPriority(SupportPriorityHeight::PARAM));
        //                // Для нового сообщения - open
        //                $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));
        //
        //                // Неизменяемая величина @TODO уточнить
        //                $supportInvariableDTO = new SupportInvariableDTO();
        //                $supportInvariableDTO->setProfile(new UserProfileUid($profile));
        //                $supportInvariableDTO->setType(new TypeProfileUid(TypeProfileFbsOzon::TYPE)); // @TODO ПЕРЕДЕЛАТЬ - добавить тип для Озон
        //
        //                $supportInvariableDTO->setTicket($customerChat->getId());
        //                $supportInvariableDTO->setTitle('OZON'); // @TODO негде взять
        //                $supportDTO->setInvariable($supportInvariableDTO);
        //            }
        //
        //            /** Сообщения чата */
        //            // получаем массив сообщений из чата
        //            $messagesChat = $this->chatHistoryRequest
        //                ->profile($profile)
        //                ->chatId($customerChat->getId())
        //                ->sortByNew()
        //                ->limit(1000) // @TODO максимальный лимит?
        //                ->getMessages();
        //
        //            /** @var OzonMessageChatDTO $chatMessage */
        //            foreach($messagesChat as $chatMessage)
        //            {
        //                // уникальный ключ для сообщения для его проверки существования в текущем чате по данным о сообщении из Ozon
        //                $deduplicator->deduplication(
        //                    [
        //                        $customerChat->getId(),
        //                        $chatMessage->getId(),
        //                        $chatMessage->getUser(),
        //                        $profile,
        //                        self::class // @TODO уточнить для чего
        //                    ]
        //                );
        //
        //                if($deduplicator->isExecuted())
        //                {
        //                    continue;
        //                }
        //
        //                // Формируем сообщение
        //                $supportMessageDTO = new SupportMessageDTO();
        //                // наши ответы помечаем админом для разделения в диалоговом окне @TODO ???
        //                $userName = $chatMessage->getUserType() === 'Seller' ? 'admin' : $chatMessage->getUser();
        //                $supportMessageDTO->setName($userName);
        //                $supportMessageDTO->setMessage(current($chatMessage->getData())); // @TODO от апи приходит массив - при сохранении нужна строка
        //
        //                $supportDTO->addMessage($supportMessageDTO);
        //                // при добавлении нового сообщения открываем чат заново
        //                $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));
        //
        //                $deduplicator->save();
        //                dump('----- chatMessage -----');
        //            }
        //
        //            $result = $this->supportHandler->handle($supportDTO);
        //
        //            if(false === $result instanceof Support)
        //            {
        //                $this->logger->critical(
        //                    sprintf(
        //                        'ozon-support: Ошибка %s при создании/обновлении чата поддержки:
        //                         Profile: %s | SupportEvent ID: %s | SupportEventInvariable.Ticker ID: %s',
        //                        $result,
        //                        (string) $profile,
        //                        $supportDTO->getEvent(),
        //                        $supportDTO->getInvariable()->getTicket(),
        //                    ),
        //                    [__FILE__.':'.__LINE__],
        //                );
        //            }
        //        }
    }
}

