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

namespace BaksDev\Ozon\Support\Api\Get\ChatMessages;

use BaksDev\Ozon\Api\Ozon;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Generator;
use InvalidArgumentException;

/**
 * Возвращает историю сообщений чата.
 */
final class GetOzonChatMessagesRequest extends Ozon
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
    private int $limit = 50;

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
     * Список сообщений чата (Доступно для продавцов с подпиской Premium Plus)
     *
     * @see https://docs.ozon.ru/api/seller/?__rr=1#operation/ChatAPI_ChatHistoryV3
     *
     * @return Generator<int, OzonMessageChatDTO>
     */
    public function findAll(): Generator|false
    {
        if(false === ($this->getProfile() instanceof UserProfileUid))
        {
            return false;
        }

        // обязательно для передачи
        if(false === $this->chat)
        {
            throw new InvalidArgumentException('Invalid argument $chat');
        }

        $json = [
            "chat_id" => $this->chat,
            "direction" => $this->sort,
            "from_message_id" => $this->fromMessage,
            "limit" => $this->limit
        ];

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                '/v3/chat/history',
                ["json" => $json]
            );

        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('ozon-support: Ошибка получения истории чата от Ozon Seller API'),
                [
                    self::class.':'.__LINE__,
                    $json,
                    $content
                ]);

            return false;
        }

        foreach($content['messages'] as $message)
        {
            yield new OzonMessageChatDTO($message, $this->chat, $this->getClient());
        }
    }
}
