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

namespace BaksDev\Ozon\Support\Api\Message\History;

use BaksDev\Ozon\Api\Ozon;
use DateInterval;
use DomainException;
use Generator;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Возвращает историю сообщений чата. По умолчанию от самого нового сообщения к старым.
 * @see https://docs.ozon.ru/api/seller/#operation/ChatAPI_ChatHistoryV2
 */
final class OzonChatHistoryRequest extends Ozon
{

    /** Все чаты (по умолчанию) */
    private string $chat;

    private int $messageId;

    public function chat(string $chat): self
    {
        $this->chat = $chat;

        return $this;
    }

    public function messageId(int $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }


    public function findAll(): Generator
    {
        $cache = $this->getCacheInit('ozon-support');

        $response = $cache->get(
            sprintf('%s-%s', 'ozon-support-chat-history', $this->chat),
            function(ItemInterface $item): ResponseInterface {

                $item->expiresAfter(DateInterval::createFromDateString('1 day'));

                return $this->TokenHttpClient()
                    ->request(
                        'POST',
                        '/v2/chat/history',
                        [
                            "json" => [
                                /** Идентификатор чата. */
                                "chat_id" => $this->chat,

                                /**
                                 * Направление сортировки сообщений:
                                 *
                                 * Forward — от старых к новым.
                                 * Backward — от новых к старым.
                                 */
                                "direction" => "Forward",

                                /**
                                 * Идентификатор сообщения, с которого начать вывод истории чата.
                                 * По умолчанию — последнее видимое сообщение.
                                 */
                                "from_message_id" => $this->messageId,

                                /** Количество значений в ответе.  */
                                "limit" => 1
                            ]
                        ]
                    );

            }
        );


        $content = $response->toArray(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical($content['code'].': '.$content['message'], [__FILE__.':'.__LINE__]);

            throw new DomainException(
                message: 'Ошибка '.self::class,
                code: $response->getStatusCode()
            );
        }

        foreach($content['messages'] as $chat)
        {
            yield new OzonChatHistoryDTO($chat);
        }
    }

}
