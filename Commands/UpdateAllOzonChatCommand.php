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
use BaksDev\Ozon\Support\Messenger\Schedules\GetOzonChatList\GetOzonChatListMessage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Добавляет новые чаты и его сообщения для существующих профилей с активными токенами Озон
 */
#[AsCommand(
    name: 'baks:ozon-support:chat:update:all',
    description: 'Добавляет/обновляет все чаты и их сообщения'
)]
final class UpdateAllOzonChatCommand extends Command
{
    private SymfonyStyle $io;

    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $ozonSupport,
        private readonly MessageDispatchInterface $messageDispatch,
        private readonly AllProfileOzonTokenInterface $allOzonTokens,
    )
    {
        parent::__construct();
        $this->logger = $ozonSupport;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $helper = $this->getHelper('profileQuestion');

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

        /** @var array<UserProfileUid> $profiles */
        $profiles = iterator_to_array($profiles);

        if(count($profiles) > 1)
        {
            $questions[] = 'Все';

            /** @var UserProfileUid $profile */
            foreach($profiles as $profile)
            {
                $questions[] = $profile->getAttr().' ( ID: '.(string) $profile.')';
            }
        }
        else
        {
            $profile = current($profiles);
            $questions[] = $profile->getAttr().' (ID: '.(string) $profile.')';
        }

        /** Объявляем вопрос с вариантами ответов */
        $profileQuestion = new ChoiceQuestion(
            question: 'Профиль пользователя',
            choices: $questions,
            default: 0
        );

        $profileName = $helper->ask($input, $output, $profileQuestion);

        if($profileName === 'Все')
        {
            /** @var UserProfileUid $profile */
            foreach($profiles as $profile)
            {
                $this->messageDispatch->dispatch(
                    message: new GetOzonChatListMessage($profile),
                );
            }
        }
        else
        {
            $this->messageDispatch->dispatch(
                message: new GetOzonChatListMessage($profile),
            );
        }

        return Command::SUCCESS;
    }
}
