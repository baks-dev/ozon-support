<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace BaksDev\Ozon\Support\Commands;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Repository\AllProfileToken\AllProfileOzonTokenInterface;
use BaksDev\Ozon\Support\Api\Chat\Get\List\GetOzonChatListRequest;
use BaksDev\Ozon\Support\Api\Chat\OzonChatDTO;
use BaksDev\Ozon\Support\Messenger\Schedules\GetOzonCustomerMessageChat\GetOzonCustomerMessageChatMessage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'baks:ozon-support:chat:new:one',
    description: 'Добавляет один выбранный чат и его сообщения'
)]
final class UpdateOneOzonChatCommand extends Command
{
    private SymfonyStyle $io;

    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $ozonSupport,
        private readonly MessageDispatchInterface $messageDispatch,
        private readonly AllProfileOzonTokenInterface $allOzonTokens,
        private readonly GetOzonChatListRequest $ozonChatListRequest,
    )
    {
        parent::__construct();
        $this->logger = $ozonSupport;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $helper = $this->getHelper('question');

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

            return Command::FAILURE;
        }

        /** @var UserProfileUid $profile */
        foreach($profiles as $profile)
        {
            $profileQuestions[] = $profile->getAttr().' | ID: '.(string) $profile;
        }

        /** Выбор профилей */
        $profileQuestion = new ChoiceQuestion(
            question: 'Профиль пользователя',
            choices: $profileQuestions,
            default: 0
        );

        $profileChoice = $helper->ask($input, $output, $profileQuestion);

        $chosenProfile = $this->searchId($profileChoice);

        // присваиваем выбранных профиль
        $this->ozonChatListRequest
            ->profile($chosenProfile);

        /** Фильтр по чатам: статус чата */
        $chatStatusQuestion = new ChoiceQuestion(
            question: 'Фильтр чатов по статусу чата',
            choices: ['Открытые', 'Закрытые', 'Все'],
            default: 0,
        );

        $chatStatusChoice = $helper->ask($input, $output, $chatStatusQuestion);

        if($chatStatusChoice === 'Открытые')
        {
            $this->ozonChatListRequest->opened();
        }

        if($chatStatusChoice === 'Закрытые')
        {
            $this->ozonChatListRequest->closed();
        }

        /** Фильтр по чатам: статус сообщений */
        $chatMessageQuestion = new ChoiceQuestion(
            question: 'Фильтр чатов по статус сообщений',
            choices: ['Чаты с непрочитанными сообщениями', 'Чаты с прочитанными сообщениями'],
            default: 0,
        );

        $chatMessagesChoice = $helper->ask($input, $output, $chatMessageQuestion);

        if($chatMessagesChoice === 'Чаты с непрочитанными сообщениями')
        {
            $this->ozonChatListRequest->unreadMessageOnly();
        }

        /**
         * Получаем массив чатов с учетом выбранных фильтров
         */
        $listChats = $this->ozonChatListRequest
            ->getListChats();

        if(false === $listChats)
        {
            $this->io->warning('Ошибка получения списка чатов');

            return Command::FAILURE;
        }

        if(false === $listChats->valid())
        {
            $this->io->warning('Не найдено чатов по выбранным фильтрам');

            return Command::FAILURE;
        }

        /** @var OzonChatDTO $chat */
        foreach($listChats as $chat)
        {
            $chatQuestions[] = 'Тип чата: '.$chat->getType().' | ID: '.$chat->getId();
        }

        /** Выбор чатов */
        $chatQuestion = new ChoiceQuestion(
            question: 'Чаты',
            choices: $chatQuestions,
        );

        $chatChoice = $helper->ask($input, $output, $chatQuestion);

        $chatId = $this->searchId($chatChoice);

        $this->messageDispatch->dispatch(
            message: new GetOzonCustomerMessageChatMessage($chatId, $chosenProfile),
        );

        return Command::SUCCESS;
    }

    /** метод для поиска ID из вывода команды */
    private function searchId(string $search): string
    {
        $id = strstr($search, 'ID: ');
        $id = str_replace('ID: ', '', $id);

        return $id;
    }
}
