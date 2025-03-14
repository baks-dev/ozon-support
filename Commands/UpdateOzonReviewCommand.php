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

namespace BaksDev\Ozon\Support\Commands;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Ozon\Repository\AllProfileToken\AllProfileOzonTokenInterface;
use BaksDev\Ozon\Support\Messenger\Schedules\GetOzonReviewList\GetOzonReviewListMessage;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'baks:ozon-support:review:update',
    description: 'Добавляет новые отзывы по товару'
)]
final class UpdateOzonReviewCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly MessageDispatchInterface $messageDispatch,
        private readonly AllProfileOzonTokenInterface $allOzonTokens,
    )
    {
        parent::__construct();
    }

    /**
     * Добавляет новые вопросы по товару для существующих профилей с активными токенами Озон
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        /** Идентификаторы профилей пользователей, у которых есть активный токен Ozon */
        $profiles = $this->allOzonTokens
            ->onlyActiveToken()
            ->findAll();

        /** @var array<UserProfileUid> $profiles */
        $profiles = iterator_to_array($profiles);

        $helper = $this->getHelper('question');

        $questions[] = 'Все';

        /** @var UserProfileUid $profile */
        foreach($profiles as $profile)
        {
            $questions[] = $profile->getAttr().' ( ID: '.(string) $profile.')';
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
                $this->update($profile);
            }
        }
        else
        {
            $UserProfileUid = null;

            foreach($profiles as $profile)
            {
                if($profile->getAttr() === $questions[$profileName])
                {
                    /* Присваиваем профиль пользователя */
                    $UserProfileUid = $profile;
                    break;
                }
            }

            if($UserProfileUid)
            {
                $this->update($UserProfileUid);
            }
        }

        $this->io->success('Отзывы успешно обновлены');

        return Command::SUCCESS;
    }

    private function update(UserProfileUid|string $profile): void
    {
        $this->io->note(sprintf('Обновляем профиль %s', $profile->getAttr()));

        $this->messageDispatch->dispatch(
            message: new GetOzonReviewListMessage($profile),
        );
    }
}