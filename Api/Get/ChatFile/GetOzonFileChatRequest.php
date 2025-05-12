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

namespace BaksDev\Ozon\Support\Api\Get\ChatFile;

use BaksDev\Ozon\Api\Ozon;
use InvalidArgumentException;

final class GetOzonFileChatRequest extends Ozon
{
    private string|false $ticket;

    private string|false $message;

    private string|false $file;

    public function ticket(string $ticket): self
    {

        $this->ticket = $ticket;

        return $this;
    }

    public function message(string $message): self
    {

        $this->message = $message;

        return $this;
    }

    public function file(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function get(): string|false
    {
        if(false === ($this->ticket || $this->message || $this->file))
        {
            throw new InvalidArgumentException('Invalid Argument Parameters');
        }

        $url = 'https://seller.ozon.ru/api/chat/v2/file/download/%s/%s/%s/%s';

        $url = sprintf($url,
            $this->getClient(), // Идентификатор клиента
            $this->ticket, // идентификатор чата (тикета)
            $this->message, // идентификатор сообщения
            $this->file // файл
        );

        $response = $this->TokenHttpClient()
            ->request(
                method: 'GET',
                url: $url,
            );

        $content = $response->getContent(false);

        if($response->getStatusCode() !== 200)
        {
            $this->logger->critical(
                sprintf('ozon-support: Ошибка получения контента: %s)', $url),
                [
                    self::class.':'.__LINE__,
                    $content
                ]);

            return false;
        }

        return $response->getContent();
    }
}
