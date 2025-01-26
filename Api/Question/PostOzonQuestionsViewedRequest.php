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

namespace BaksDev\Ozon\Support\Api\Question;

use BaksDev\Ozon\Api\Ozon;

final class PostOzonQuestionsViewedRequest extends Ozon
{
    private array $question;

    public function question(string|array $question): self
    {
        if(is_array($question))
        {
            $this->question = $question;
        }

        if(is_string($question))
        {
            $this->question[] = $question;
        }

        return $this;
    }

    /**
     * Изменить статус вопросов на просмотренный
     *
     * @see https://docs.ozon.ru/api/seller/#operation/Question_ChangeStatus
     */
    public function update(): bool
    {

        /** TODO: тод временно недоступен */
        return true;

        if($this->isExecuteEnvironment() === false)
        {
            return false;
        }

        if(empty($this->question))
        {
            return false;
        }

        $json = [
            'question_ids' => $this->question,
            "status" => "VIEWED"
        ];


        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                '/v1/question/change_status',
                ["json" => $json]
            );

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical('ozon-support: Ошибка обновления статусов вопросов',
                [
                    self::class.':'.__LINE__,
                    $response->toArray(false),
                    $json
                ]);
        }

        return $response->getStatusCode() === 200;
    }
}
