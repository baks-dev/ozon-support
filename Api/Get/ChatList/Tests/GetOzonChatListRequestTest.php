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

namespace BaksDev\Ozon\Support\Api\Get\ChatList\Tests;

use BaksDev\Ozon\Orders\Type\ProfileType\TypeProfileFbsOzon;
use BaksDev\Ozon\Support\Api\Get\ChatList\GetOzonChatListRequest;
use BaksDev\Ozon\Support\Api\Get\ChatList\OzonChatDTO;
use BaksDev\Ozon\Type\Authorization\OzonAuthorizationToken;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('ozon-support')]
#[When(env: 'test')]
class GetOzonChatListRequestTest extends KernelTestCase
{
    private static OzonAuthorizationToken $authorization;

    public static function setUpBeforeClass(): void
    {
        self::$authorization = new OzonAuthorizationToken(
            new UserProfileUid('018d464d-c67a-7285-8192-7235b0510924'),
            $_SERVER['TEST_OZON_TOKEN'],
            TypeProfileFbsOzon::TYPE,
            $_SERVER['TEST_OZON_CLIENT'],
            $_SERVER['TEST_OZON_WAREHOUSE'],
            '10',
            0,
            false,
            false,
        );
    }

    public function testComplete(): void
    {
        self::assertTrue(true);

        /** @var GetOzonChatListRequest $ozonChatListRequest */
        $ozonChatListRequest = self::getContainer()->get(GetOzonChatListRequest::class);
        $ozonChatListRequest->TokenHttpClient(self::$authorization);

        $chats = $ozonChatListRequest
            ->getListChats();


        //dd(iterator_to_array($chats));

        /** @var OzonChatDTO $chat */
        foreach($chats as $chat)
        {
            self::assertIsString($chat->getId());
            self::assertIsString($chat->getStatus());
            self::assertIsString($chat->getType());
            self::assertTrue($chat->getCreated() instanceof DateTimeImmutable);

            self::assertTrue(is_numeric($chat->getFirstUnreadMessage()));
            self::assertTrue(is_numeric($chat->getUnreadMessageCount()));
            self::assertTrue(is_numeric($chat->getLastMessage()));
        }
    }
}
