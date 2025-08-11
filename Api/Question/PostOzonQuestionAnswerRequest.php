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
use InvalidArgumentException;

final class PostOzonQuestionAnswerRequest extends Ozon
{
    private string|false $question = false;

    private int|false $sku = false;

    private string|false $text = false;

    /**
     * Идентификатор вопроса.
     */
    public function question(string $question): self
    {
        $this->question = $question;

        return $this;
    }

    /**
     * SKU товара.
     */
    public function sku(string|int $sku): self
    {
        $this->sku = (int) $sku;
        return $this;
    }

    /**
     * Текст ответа объёмом от 2 до 3000 символов.
     */
    public function text(string $text): self
    {
        $this->text = mb_substr($text, 0, 3000);
        return $this;
    }


    /**
     * Создать ответ на вопрос
     *
     * @see https://docs.ozon.ru/api/seller/#operation/QuestionAnswer_Create
     */
    public function create(): bool
    {
        /**
         * Выполнять операции запроса ТОЛЬКО в PROD окружении
         */
        if($this->isExecuteEnvironment() === false)
        {
            return false;
        }

        // обязательно для передачи
        if(false === $this->question)
        {
            throw new InvalidArgumentException('Invalid Argument Question');
        }

        // обязательно для передачи
        if(false === $this->sku)
        {
            throw new InvalidArgumentException('Invalid Argument Sku');
        }

        // обязательно для передачи
        if(false === $this->text)
        {
            throw new InvalidArgumentException('Invalid Argument text');
        }

        $json = [
            "question_id" => $this->question,
            "sku" => $this->sku,
            "text" => $this->text,
        ];

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                '/v1/question/answer/create',
                ["json" => $json]
            );

        if($response->getStatusCode() !== 200)
        {
            $result = $response->toArray(false);

            if(str_contains($result['message'], 'checkSellerPremiumPlus'))
            {
                $this->logger->critical('ozon-support: Ошибка при ответе на вопрос',
                    [
                        self::class.':'.__LINE__,
                        $result,
                        $json,
                    ]);
                return true;
            }

            $this->logger->critical('ozon-support: Ошибка при ответе на вопрос',
                [
                    self::class.':'.__LINE__,
                    $result,
                    $json
                ]);
        }

        return $response->getStatusCode() === 200;
    }
}
