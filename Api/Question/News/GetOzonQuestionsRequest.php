<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Ozon\Support\Api\Question\News;

use BaksDev\Ozon\Api\Ozon;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateInterval;
use DateTimeImmutable;
use Generator;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
final class GetOzonQuestionsRequest extends Ozon
{
    private ?string $last = null;

    /**
     * Статусы вопроса:
     *
     * NEW — новый,
     * ALL — все вопросы,
     * VIEWED — просмотренный,
     * PROCESSED — обработанный,
     * UNPROCESSED — необработанный.
     */
    // private string $status = 'ALL';
    private string $status = 'ALL';

    /**
     * Список вопросов
     *
     * @see https://docs.ozon.ru/api/seller/#operation/Question_List
     */
    public function findAll(): false|Generator
    {
        while(true)
        {
            $json = [
                'filter' => [

                    "date_from" => new DateTimeImmutable()
                        ->sub(DateInterval::createFromDateString('1 day'))
                        ->modify('-1 day')
                        ->format('Y-m-d\TH:i:s\Z'),

                    "date_to" => (new DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),

                    "status" => $this->status,
                ],

                'last_id' => $this->last,
            ];

            $response = $this->TokenHttpClient()
                ->request(
                    'POST',
                    '/v1/question/list',
                    ["json" => $json],
                );

            $content = $response->toArray(false);

            if($response->getStatusCode() !== 200)
            {
                // код ошибки, если пользователь не премиум
                if((int) $content['code'] === 7)
                {
                    return false;
                }

                $this->logger->critical(
                    sprintf('ozon-support: Ошибка получения списка вопросов'),
                    [
                        self::class.':'.__LINE__,
                        $content,
                    ]);

                return false;
            }

            if(empty($content['questions']))
            {
                return false;
            }

            if($content['last_id'] === '00000000-0000-0000-0000-000000000000')
            {
                return false;
            }

            $this->last = $content['last_id'];

            foreach($content['questions'] as $question)
            {
                /** Пропускаем вопросы с ответом */
                if($question['status'] === 'PROCESSED')
                {
                    continue;
                }

                yield new OzonQuestionDTO($question);
            }
        }
    }
}
