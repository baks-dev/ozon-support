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

namespace BaksDev\Ozon\Support\Api\Chat\Get\History\Tests;

use BaksDev\Ozon\Support\Api\Chat\Get\History\GetOzonChatHistoryRequest;
use BaksDev\Ozon\Support\Api\Message\OzonMessageChatDTO;
use BaksDev\Ozon\Type\Authorization\OzonAuthorizationToken;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group ozon-support
 * @group ozon-support-api
 */
#[When(env: 'test')]
class GetOzonChatHistoryRequestTest extends KernelTestCase
{
    private static OzonAuthorizationToken $Authorization;

    public static function setUpBeforeClass(): void
    {
        self::$Authorization = new OzonAuthorizationToken(
            new UserProfileUid(),
            $_SERVER['TEST_OZON_TOKEN'],
            $_SERVER['TEST_OZON_CLIENT'],
            $_SERVER['TEST_OZON_WAREHOUSE']
        );
    }

    public function testRequest(): void
    {
        /** @var GetOzonChatHistoryRequest $ozonChatHistoryRequest */
        $ozonChatHistoryRequest = self::getContainer()->get(GetOzonChatHistoryRequest::class);
        $ozonChatHistoryRequest->TokenHttpClient(self::$Authorization);

        $messages = $ozonChatHistoryRequest
            ->chatId('90145814-406d-4e46-8b43-d5287f9052c2')
            ->limit(1)
            ->getMessages();

        dd(iterator_to_array($messages));

        if($messages->valid())
        {
            /** @var OzonMessageChatDTO $ozonChatMessageDTO */
            $ozonChatMessageDTO = $messages->current();

            self::assertNotNull($ozonChatMessageDTO->getId());
            self::assertIsString($ozonChatMessageDTO->getId());
        }
        else
        {
            self::assertFalse($messages->valid());
        }

    }
}
