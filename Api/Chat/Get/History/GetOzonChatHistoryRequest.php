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

namespace BaksDev\Ozon\Support\Api\Chat\Get\History;

use BaksDev\Ozon\Api\Ozon;
use BaksDev\Ozon\Support\Api\Message\OzonMessageChatDTO;
use Generator;

/**
 * Возвращает историю сообщений чата.
 */
final class GetOzonChatHistoryRequest extends Ozon
{
    /**
     * Идентификатор чата.
     *
     * Все чаты (по умолчанию)
     */
    private string|false $chat = false;

    /**
     * Направление сортировки сообщений:
     *
     * Forward — от старых к новым.
     * Backward — от новых к старым.
     */
    private string $sort = 'Backward';

    /**
     * Количество значений в ответе.
     *
     * Значение по умолчанию — 50
     * Максимальное значение — 1000.
     */
    private int $limit = 30;

    /**
     * Идентификатор сообщения, с которого начать вывод истории чата.
     * По умолчанию — последнее видимое сообщение.
     */
    private int|null $fromMessage = null;

    public function chatId(string $chat): self
    {
        $this->chat = $chat;

        return $this;
    }

    public function fromMessage(int $messageId): self
    {
        $this->fromMessage = $messageId;

        return $this;
    }

    /**
     * Направление сортировки сообщений: от старых к новым
     */
    public function sortByOld(): self
    {
        $this->sort = 'Forward';

        return $this;
    }

    /**
     * Направление сортировки сообщений: от новых к старым
     */
    public function sortByNew(): self
    {
        $this->sort = 'Backward';

        return $this;
    }

    /**
     * Количество значений в ответе.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Список сообщений чата
     * @see https://docs.ozon.ru/api/seller/#operation/ChatAPI_ChatHistoryV2
     *
     * @return Generator<int, OzonMessageChatDTO>
     */
    public function getMessages(): Generator|false
    {
        // обязательно для передачи
        if(false === $this->chat)
        {
            throw new \InvalidArgumentException('Invalid argument exception chat');
        }

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                '/v2/chat/history',
                [
                    "json" => [
                        "chat_id" => $this->chat,
                        "direction" => $this->sort,
                        "from_message_id" => $this->fromMessage,
                        "limit" => $this->limit
                    ]
                ]
            );

        if($responseCode = $response->getStatusCode() !== 200)
        {
            $error = $response->getContent(false);

            $this->logger->critical(
                sprintf('Ошибка получения истории чата от Ozon Seller API (Response Code: %s, INFO: %s)', (string) $responseCode, $error),
                [__FILE__.':'.__LINE__]);

            return false;
        }

        $content = $response->toArray(false);

        foreach($content['messages'] as $message)
        {
            yield new OzonMessageChatDTO($message);
        }
    }
}
