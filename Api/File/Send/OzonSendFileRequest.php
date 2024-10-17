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

namespace BaksDev\Ozon\Support\Api\File\Send;

use BaksDev\Ozon\Api\Ozon;
use DomainException;

/**
 * Отправляет файл в существующий чат по его идентификатору.
 * @see https://docs.ozon.ru/api/seller/#operation/ChatAPI_ChatSendFile
 */
final class OzonSendFileRequest extends Ozon
{

    /**
     * id - Идентификатор чата
     * content - Файл в виде строки base64.
     * name - Название файла с расширением.
     */
    public function send(string $id, string $content, string $name): bool
    {

        /**
         * Выполнять операции запроса ТОЛЬКО в PROD окружении
         */
        if($this->isExecuteEnvironment() === false)
        {
            return true;
        }

        $response = $this->TokenHttpClient()
            ->request(
                'POST',
                '/v1/chat/send/file',
                [
                    "json" => [
                        /** Файл в виде строки base64. */
                        'base64_content' => $content,

                        /** Идентификатор чата */
                        'chat_id' => $id,

                        /** Название файла с расширением. */
                        'name' => $name
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
