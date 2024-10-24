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

namespace BaksDev\Ozon\Support\Api\Message\Post\MarkReading;

use BaksDev\Ozon\Api\Ozon;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Метод для отметки выбранного сообщения и сообщений до него прочитанными.
 */
#[Autoconfigure(public: true)]
final class MarkReadingOzonMessageChatRequest extends Ozon
{
    /** Идентификатор чата */
    private string|false $chatId = false;

    /** Идентификатор сообщения. */
    private int|null $fromMessage = null;

    public function fromMessage(int $messageId): self
    {
        $this->fromMessage = $messageId;

        return $this;
    }

    public function chatId(string $chat): self
    {
        $this->chatId = $chat;

        return $this;
    }

    /**
     * Отметить выбранное сообщения и сообщений ДО НЕГО прочитанными
     *
     * @see https://docs.ozon.ru/api/seller/#tag/ChatAPI
     */
    public function postReading(): bool
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
        if(false === $this->fromMessage)
        {
            throw new \InvalidArgumentException('Invalid argument exception messageId');
        }

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                'v2/chat/read',
                [
                    "json" => [
                        'chat_id' => $this->chatId,
                        'from_message_id' => $this->fromMessage,
                    ]
                ]
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical($content['code'].': '.$content['message'], [self::class.':'.__LINE__]);
        }

        return true;
    }
}
