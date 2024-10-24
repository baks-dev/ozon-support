<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Ozon\Support\Api\Message\Post\Send;

use BaksDev\Ozon\Api\Ozon;
use DomainException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Отправляет сообщение в существующий чат по его идентификатору.
 * @see https://docs.ozon.ru/api/seller/#tag/ChatAPI
 */
#[Autoconfigure(public: true)]
final class SendOzonChatMessageRequest extends Ozon
{
    /** Идентификатор чата */
    private string|false $chatId = false;

    /** Текст сообщения в формате plain text от 1 до 1000 символов */
    private string|false $message = false;

    public function chatId(string $chat): self
    {
        $this->chatId = $chat;

        return $this;
    }

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /** Отправить сообщение */
    public function postMessage(): bool
    {
        /**
         * Выполнять операции запроса ТОЛЬКО в PROD окружении
         */
        if($this->isExecuteEnvironment() === false)
        {
            return false;
        }

        // обязательно для передачи
        if(false === $this->chatId)
        {
            throw new \InvalidArgumentException('Invalid argument exception chat');
        }

        // обязательно для передачи
        if(false === $this->message)
        {
            throw new \InvalidArgumentException('Invalid argument exception text');
        }

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                '/v1/chat/send/message',
                [
                    "json" => [
                        'chat_id' => $this->chatId,
                        'text' => $this->message,
                    ]
                ]
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {

            $this->logger->critical($content['code'].': '.$content['message'], [self::class.':'.__LINE__]);

            throw new DomainException(
                message: 'Ошибка '.self::class,
                code: $response->getStatusCode()
            );
        }

        return true;
    }
}
