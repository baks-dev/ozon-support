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

namespace BaksDev\Ozon\Support\Repository\FindExistOzonMessageChat;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Support\Entity\Event\SupportEvent;
use BaksDev\Support\Entity\Message\SupportMessage;
use BaksDev\Support\Entity\Support;

final class FindExistMessageChatRepository
{
    private string|false $message = false;

    public function __construct(
        private readonly DBALQueryBuilder $DBALQueryBuilder,
    ) {}

    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Проверка существования сообщения по его внешнему идентификатору
     */
    public function isExist(): bool
    {
        if(false === $this->message)
        {
            throw new \InvalidArgumentException('Invalid Argument message');
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->from(SupportMessage::class, 'support_message')
            ->where(' support_message.external = :message')
            ->setParameter('message', $this->message);

        $dbal
            ->join(
                'support_message',
                SupportEvent::class,
                'support_event',
                'support_message.event = support_event.id'
            );

        $dbal
            ->join(
                'support_event',
                Support::class,
                'support',
                'support_event.id = support.event'
            );

        // @TODO включаем кеш по такому имени?
        $dbal->enableCache('ozon-support');

        return $dbal->fetchExist();
    }
}
