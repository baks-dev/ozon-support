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

namespace BaksDev\Ozon\Support\Messenger\Schedules\GetOzonQuestion;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Ozon\Products\Api\Card\Identifier\GetOzonCardNameRequest;
use BaksDev\Ozon\Repository\OzonTokensByProfile\OzonTokensByProfileInterface;
use BaksDev\Ozon\Support\Api\Question\News\GetOzonQuestionsRequest;
use BaksDev\Ozon\Support\Api\Question\News\OzonQuestionDTO;
use BaksDev\Ozon\Support\Api\Question\PostOzonQuestionsViewedRequest;
use BaksDev\Ozon\Support\Type\OzonQuestionProfileType;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Repository\ExistTicket\ExistSupportTicketInterface;
use BaksDev\Support\Type\Priority\SupportPriority;
use BaksDev\Support\Type\Priority\SupportPriority\Collection\SupportPriorityLow;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusOpen;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Добавляет новые вопросы Ozon
 */
#[AsMessageHandler(priority: 0)]
final class OzonQuestionDispatcher
{
    public function __construct(
        #[Target('ozonSupportLogger')] private LoggerInterface $logger,
        private readonly DeduplicatorInterface $deduplicator,
        private readonly ExistSupportTicketInterface $ExistSupportTicketRepository,
        private readonly GetOzonQuestionsRequest $GetOzonQuestionsRequest,
        private readonly PostOzonQuestionsViewedRequest $PostOzonQuestionsViewedRequest,
        private readonly GetOzonCardNameRequest $GetOzonCardNameRequest,
        private readonly SupportHandler $SupportHandler,
        private OzonTokensByProfileInterface $OzonTokensByProfile,
    ) {}


    public function __invoke(OzonQuestionMessage $message): void
    {
        /**
         * Дедубликатор обработчика
         */

        $DeduplicatorExecuted = $this->deduplicator
            ->expiresAfter('1 minute')
            ->namespace('ozon-support')
            ->deduplication([$message->getProfile(), self::class]);

        if($DeduplicatorExecuted->isExecuted())
        {
            return;
        }

        $DeduplicatorExecuted->save();


        $profile = $message->getProfile();

        /** Получаем все токены профиля */

        $tokensByProfile = $this->OzonTokensByProfile
            ->onlyCardUpdate()
            ->findAll($message->getProfile());

        if(false === $tokensByProfile || false === $tokensByProfile->valid())
        {
            return;
        }


        foreach($tokensByProfile as $OzonTokenUid)
        {
            /**
             * Получаем новые вопросы
             *
             * @see GetOzonQuestionsRequest
             */

            $questions = $this->GetOzonQuestionsRequest
                ->forTokenIdentifier($OzonTokenUid)
                ->findAll();

            if(false === $questions->valid())
            {
                $DeduplicatorExecuted->delete();
                continue;
            }


            /** @see OzonQuestionDTO $question */
            foreach($questions as $question)
            {
                $deduplicator = $this->deduplicator
                    ->expiresAfter('1 day')
                    ->deduplication([$question->getId(), self::class]);

                if($deduplicator->isExecuted())
                {
                    continue;
                }

                /**
                 * Пропускаем, если указанный тикет добавлен
                 *
                 * @see ExistSupportTicketInterface
                 */
                $questionExist = $this->ExistSupportTicketRepository
                    ->ticket($question->getId())
                    ->exist();

                if($questionExist)
                {
                    continue;
                }

                /**
                 * @see SupportEvent
                 */
                $SupportDTO = (new SupportDTO()) // done
                ->setPriority(new SupportPriority(SupportPriorityLow::class)) // CustomerMessage - высокий приоритет
                ->setStatus(new SupportStatus(SupportStatusOpen::class)); // Для нового сообщения - StatusOpen

                /** Присваиваем токен для последующего поиска */
                $SupportDTO->getToken()->setValue($OzonTokenUid);


                /**
                 * @see SupportInvariable
                 */

                $title = $this->GetOzonCardNameRequest
                    ->forTokenIdentifier($OzonTokenUid)
                    ->sku($question->getSku())->find() ?: null;

                $article = false;

                // Используем регулярное выражение для извлечения текста до и внутри круглых скобок
                preg_match('/^(.*?)s*\((.*?)\)$/', (string) $title, $matches);

                if(count($matches) === 3)
                {
                    $title = trim($matches[1]); // Текст до круглых скобок
                    $article = trim($matches[2]); // Текст внутри круглых скобок
                }

                $supportInvariableDTO = new SupportInvariableDTO()
                    //->setProfile($message->getProfile())
                    ->setType(new TypeProfileUid(OzonQuestionProfileType::class))
                    ->setTicket($question->getId())
                    ->setTitle($title);

                $SupportDTO->setInvariable($supportInvariableDTO);

                /**
                 * @see SupportMessage
                 */

                $text = $question->getText();

                /** Добавляем к тексту ссылку с артикулом */
                if($article)
                {
                    $article = sprintf('<p><article 
                        class="d-flex align-items-center gap-1 text-primary pointer copy small"
                        data-copy="%s"
                        ><svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="14" height="14" fill="currentColor" viewBox="0 0 115.77 122.88">
                        <path d="M89.62,13.96v7.73h12.19h0.01v0.02c3.85,0.01,7.34,1.57,9.86,4.1c2.5,2.51,4.06,5.98,4.07,9.82h0.02v0.02 v73.27v0.01h-0.02c-0.01,3.84-1.57,7.33-4.1,9.86c-2.51,2.5-5.98,4.06-9.82,4.07v0.02h-0.02h-61.7H40.1v-0.02 c-3.84-0.01-7.34-1.57-9.86-4.1c-2.5-2.51-4.06-5.98-4.07-9.82h-0.02v-0.02V92.51H13.96h-0.01v-0.02c-3.84-0.01-7.34-1.57-9.86-4.1 c-2.5-2.51-4.06-5.98-4.07-9.82H0v-0.02V13.96v-0.01h0.02c0.01-3.85,1.58-7.34,4.1-9.86c2.51-2.5,5.98-4.06,9.82-4.07V0h0.02h61.7 h0.01v0.02c3.85,0.01,7.34,1.57,9.86,4.1c2.5,2.51,4.06,5.98,4.07,9.82h0.02V13.96L89.62,13.96z M79.04,21.69v-7.73v-0.02h0.02 c0-0.91-0.39-1.75-1.01-2.37c-0.61-0.61-1.46-1-2.37-1v0.02h-0.01h-61.7h-0.02v-0.02c-0.91,0-1.75,0.39-2.37,1.01 c-0.61,0.61-1,1.46-1,2.37h0.02v0.01v64.59v0.02h-0.02c0,0.91,0.39,1.75,1.01,2.37c0.61,0.61,1.46,1,2.37,1v-0.02h0.01h12.19V35.65 v-0.01h0.02c0.01-3.85,1.58-7.34,4.1-9.86c2.51-2.5,5.98-4.06,9.82-4.07v-0.02h0.02H79.04L79.04,21.69z M105.18,108.92V35.65v-0.02 h0.02c0-0.91-0.39-1.75-1.01-2.37c-0.61-0.61-1.46-1-2.37-1v0.02h-0.01h-61.7h-0.02v-0.02c-0.91,0-1.75,0.39-2.37,1.01 c-0.61,0.61-1,1.46-1,2.37h0.02v0.01v73.27v0.02h-0.02c0,0.91,0.39,1.75,1.01,2.37c0.61,0.61,1.46,1,2.37,1v-0.02h0.01h61.7h0.02 v0.02c0.91,0,1.75-0.39,2.37-1.01c0.61-0.61,1-1.46,1-2.37h-0.02V108.92L105.18,108.92z"></path>
                        </svg> 
                        Артикул: %s</article></p>', $article, $article);

                    $text .= str_replace(PHP_EOL, " ", $article);
                }


                // $text .= sprintf('<p><a target="_blank" href="https://ozon.ru/product/%s">Перейти на Ozon</a></p>', $question->getSku());

                /** Добавляем ссылку на страницу товара */

                $SupportMessageDTO = new SupportMessageDTO()
                    ->setExternal($question->getSku()) // Идентификатор продукта SKU
                    ->setName($question->getName()) // Имя автора вопроса.
                    ->setMessage($text) // Текст вопроса
                    ->setDate($question->getCreated()) // Дата вопроса
                    ->setInMessage();

                $SupportDTO->addMessage($SupportMessageDTO);

                /**
                 * @see SupportHandler
                 */
                $handle = $this->SupportHandler->handle($SupportDTO);

                if(false === ($handle instanceof Support))
                {
                    $this->logger->critical(
                        sprintf('avito-support: Ошибка %s при добавлении вопроса', $handle),
                        [self::class.':'.__LINE__],
                    );

                    continue;
                }

                $deduplicator->save();

                /** Добавляем в массив идентификатор ответа для отметки о прочитанном */
                $this->PostOzonQuestionsViewedRequest
                    ->forTokenIdentifier($OzonTokenUid)
                    ->question($question->getId());
            }

            /**
             * Отмечаем все вопросы как прочитанными
             *
             * @see PostOzonQuestionsViewedRequest
             */
            $viewed = $this->PostOzonQuestionsViewedRequest
                ->forTokenIdentifier($OzonTokenUid)
                ->update();

            if(false === $viewed)
            {
                $this->logger->warning('Ошибка при обновлении статусов вопросов');
            }
        }

        $DeduplicatorExecuted->delete();
    }
}
