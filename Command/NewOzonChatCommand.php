<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BaksDev\Ozon\Support\Command;

use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Ozon\Orders\Type\ProfileType\TypeProfileFbsOzon;
use BaksDev\Ozon\Repository\AllProfileToken\AllProfileOzonTokenInterface;
use BaksDev\Ozon\Support\Api\Chat\Get\History\GetOzonChatHistoryRequest;
use BaksDev\Ozon\Support\Api\Chat\Get\List\GetOzonChatListRequest;
use BaksDev\Ozon\Support\Api\Chat\OzonChatDTO;
use BaksDev\Ozon\Support\Api\Message\OzonMessageChatDTO;
use BaksDev\Ozon\Support\Repository\CurrentSupportByOzonChat\CurrentSupportByOzonChatRepository;
use BaksDev\Ozon\Support\Repository\FindExistOzonMessageChat\FindExistMessageChatRepository;
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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Добавляет новые чаты для существующих профилей с активными токенами Озон
 */
#[AsCommand(
    name: 'baks:ozon-support:chat:new',
    description: 'Добавляет новые чаты для существующих профилей с активными токенами Озон'
)]
final class NewOzonChatCommand extends Command
{
    private SymfonyStyle $io;

    private LoggerInterface $logger;

    private bool $isAddMessage = false;

    private ?\DateTimeImmutable $lastMessageDate = null;

    public function __construct(
        LoggerInterface $ozonSupport,
        private readonly DeduplicatorInterface $deduplicator,
        private readonly AllProfileOzonTokenInterface $allOzonTokens,
        private readonly GetOzonChatListRequest $ozonChatListRequest,
        private readonly GetOzonChatHistoryRequest $chatHistoryRequest,
        private readonly CurrentSupportByOzonChatRepository $supportByOzonChat,
        private readonly FindExistMessageChatRepository $messageExist,
        private readonly SupportHandler $supportHandler,
    )
    {
        parent::__construct();
        $this->logger = $ozonSupport;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** Идентификаторы профилей пользователей, у которых есть активный токен Ozon */
        $profiles = $this->allOzonTokens
            ->onlyActiveToken()
            ->findAll();

        if(false === $profiles->valid())
        {
            $this->logger->warning(
                'Профили с активными токенами Ozon не найдены',
                [__FILE__.':'.__LINE__],
            );
        }

        foreach($profiles as $profile)
        {
            /**
             * Получаем массив чатов:
             * - открытые
             */
            $listChats = $this->ozonChatListRequest
                ->profile($profile)
                ->opened()
                ->getListChats();

            if(false === $listChats)
            {
                // @TODO добавить описание
                $output->writeln('');

                return Command::FAILURE;
            }

            // только чаты с покупателями
            $customerChats = array_filter(iterator_to_array($listChats), function(OzonChatDTO $chat) {
                return $chat->getType() === 'Buyer_Seller';
            });

            if(empty($customerChats))
            {
                $this->logger->warning(
                    'Нет непрочитанных чатов с покупателями',
                    [__FILE__.':'.__LINE__],
                );

                return Command::FAILURE;
            }

            /** @var OzonChatDTO $customerChat */
            foreach($customerChats as $customerChat)
            {
                $ticket = $customerChat->getId();

                $supportDTO = new SupportDTO();

                // подготавливаю DTO для события
                $supportDTO->setPriority(new SupportPriority(SupportPriorityHeight::PARAM)); // Customer - высокий приоритет
                $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM)); // Для нового сообщения - open

                $supportInvariableDTO = new SupportInvariableDTO();
                $supportInvariableDTO->setProfile($profile);
                $supportInvariableDTO->setType(new TypeProfileUid(TypeProfileFbsOzon::TYPE)); // @TODO ПЕРЕДЕЛАТЬ - добавить тип для Озон

                // уникальный идентификатор чата в Озон
                $supportInvariableDTO->setTicket($ticket);

                $supportInvariableDTO->setTitle('OZON'); // @TODO прикрутить анализ текста из data
                $supportDTO->setInvariable($supportInvariableDTO);

                // текущее событие чата по идентификатору чата (тикета) из Ozon
                $support = $this->supportByOzonChat->find($ticket);

                if($support)
                {
                    // пересохраняю событие с новыми данными
                    $support->getDto($supportDTO);
                }


                /** Сообщения чата */
                // получаем массив сообщений из чата
                $ozonMessagesChat = $this->chatHistoryRequest
                    ->profile($profile)
                    ->chatId($ticket)
                    ->sortByNew()
                    ->limit(1000)
                    ->getMessages();

                if(false === $ozonMessagesChat)
                {
                    // @TODO добавить описание
                    $output->writeln('');

                    return Command::FAILURE;
                }

                /**
                 * Фильтруем сообщения:
                 * - кроме type seller;
                 */
                $ozonMessagesChat = array_filter(iterator_to_array($ozonMessagesChat),
                    function(OzonMessageChatDTO $message) {
                        return $message->getUserType() !== 'Seller';
                    });

                if(empty($ozonMessagesChat))
                {
                    $this->logger->info(
                        'Нет непрочитанных сообщений',
                        [__FILE__.':'.__LINE__],
                    );

                    return Command::SUCCESS;
                }

                //  для отслеживания добавления созданных сообщений в чат
                $this->deduplicator
                    ->namespace('ozon-support')
                    ->expiresAfter(DateInterval::createFromDateString('1 minute')); // @TODO на сколько временя сохранять?

                /** @var OzonMessageChatDTO $ozonMessage */
                foreach($ozonMessagesChat as $ozonMessage)
                {
                    // уникальный ключ сообщения для его проверки существования в текущем чате по данным о сообщении из Ozon
                    $this->deduplicator->deduplication(
                        [
                            $ticket,
                            $ozonMessage->getId(),
                            $ozonMessage->getUser(),
                            $profile,
                            self::class,
                        ]
                    );

                    // проверка в дедубликаторе
                    if($this->deduplicator->isExecuted())
                    {
                        // @TODO добавить подробностей
                        $this->logger->warning(
                            'deduplicator: сообщение уже добавлено в чат:'.$supportDTO->getEvent(),
                            [__FILE__.':'.__LINE__],
                        );

                        continue;
                    }

                    // проверка в БД
                    $messageIsExist = $this->messageExist
                        ->message($ozonMessage->getId())
                        ->isExist();

                    if(true === $messageIsExist)
                    {
                        // @TODO добавить подробностей
                        $this->logger->warning(
                            'repository: сообщение уже добавлено в чат:'.$supportDTO->getEvent(),
                            [__FILE__.':'.__LINE__],
                        );

                        continue;
                    }

                    // подготовка DTO для нового сообщения
                    $supportMessageDTO = new SupportMessageDTO();
                    $supportMessageDTO->setName($ozonMessage->getUser());
                    $supportMessageDTO->setMessage(current($ozonMessage->getData())); // @TODO разобраться с данными из массива data
                    $supportMessageDTO->setDate($ozonMessage->getCreated());

                    // уникальный идентификатор сообщения в Озон
                    $supportMessageDTO->setExternal($ozonMessage->getId());

                    $supportDTO->addMessage($supportMessageDTO);

                    // при добавлении нового сообщения открываем чат заново
                    $supportDTO->setStatus(new SupportStatus(SupportStatusOpen::PARAM));

                    $this->isAddMessage = true;
                    //                    $this->deduplicator->save();
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

                    if(true === $result instanceof Support)
                    {
                        $output->writeln(sprintf('Чат успешно добавлен: ID %s | Event: %s', $result->getId(), $result->getEvent()));
                    }
                }
            }
        }

        //        $output->writeln('');

        return Command::SUCCESS;
    }
}
