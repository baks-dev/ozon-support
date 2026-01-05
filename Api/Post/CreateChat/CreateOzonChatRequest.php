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

namespace BaksDev\Ozon\Support\Api\Post\CreateChat;

use BaksDev\Ozon\Api\Ozon;
use InvalidArgumentException;

final class CreateOzonChatRequest extends Ozon
{
    private string $order;

    /** Идентификатор чата */
    public function order(string|int $order): self
    {
        $order = str_replace('O-', '', (string) $order);

        $this->order = $order;

        return $this;
    }


    /**
     * Создать новый чат
     *
     * Создает новый чат с покупателем по отправлению. Например, чтобы уточнить адрес или модель товара.
     *
     * Для отправлений:
     *
     * FBO — начать чат может только покупатель.
     * FBS и rFBS — вы можете открыть чат в течение 72 часов после оплаты или доставки отправления.
     *
     * @see https://docs.ozon.ru/api/seller/#operation/ChatAPI_ChatStart
     */
    public function create(): string|bool
    {
        if($this->isExecuteEnvironment() === false)
        {
            $this->logger->critical('Запрос может быть выполнен только в PROD окружении', [self::class.':'.__LINE__]);
            return true;
        }

        // обязательно для передачи
        if(empty($this->order))
        {
            throw new InvalidArgumentException('Invalid Argument Order');
        }

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                '/v1/chat/start',
                ["json" => ['posting_number' => $this->order]],
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('%s: Ошибка создания чата с пользователем по отправлению', $this->order),
                [self::class.':'.__LINE__, $content],
            );

            return false;
        }

        return $content['result']['chat_id'] ?? false;
    }
}