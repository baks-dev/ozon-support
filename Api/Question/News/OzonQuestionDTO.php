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

namespace BaksDev\Ozon\Support\Api\Question\News;

use DateTimeImmutable;
use DateTimeZone;

final readonly class OzonQuestionDTO
{
    /** Идентификатор вопроса. */
    private string $id;

    /** Идентификатор SKU товара */
    private int $sku;

    /** Имя автора вопроса. */
    private string $name;

    /** Дата публикации вопроса. */
    private DateTimeImmutable $created;

    /** Текст вопроса. */
    private string $text;

    public function __construct(array $data)
    {
        $this->name = $data["author_name"]; // Имя автора вопроса.
        $this->id = $data["id"]; // : "019294ff-6888-7009-89d8-26569e4e450d",

        $moscowTimezone = new DateTimeZone(date_default_timezone_get());
        $this->created = (new DateTimeImmutable($data['published_at']))->setTimezone($moscowTimezone);

        $this->text = $data["text"];
        $this->sku = $data["sku"];

        //$data["answers_count"]; // Количество ответов на вопрос.
        //$data["sku"]; // : 646399170,
        //$data["product_url"]; // : "https://www.ozon.ru/product/1649246352/",
        //$data["question_link"]; // : "https://www.ozon.ru/product/1649246352/questions/?qid=290180206&utm_campaign=reviews_sc_link&utm_medium=share_button&utm_source=smm",

    }

    /** Идентификатор вопроса. */
    public function getId(): string
    {
        return $this->id;
    }

    /** Идентификатор SKU товара */
    public function getSku(): int
    {
        return $this->sku;
    }

    /** Имя автора вопроса. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Дата публикации вопроса. */
    public function getCreated(): DateTimeImmutable
    {
        return $this->created;
    }

    /** Текст вопроса. */
    public function getText(): string
    {
        return $this->text;
    }

}
