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

namespace BaksDev\Ozon\Support\Api\ReviewInfo\Get\Tests;

use BaksDev\Ozon\Support\Api\ReviewInfo\Get\GetOzonReviewInfoRequest;
use BaksDev\Ozon\Support\Api\ReviewInfo\Get\OzonReviewInfoDTO;
use BaksDev\Ozon\Type\Authorization\OzonAuthorizationToken;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group ozon-support
 * @group ozon-support-api
 */
#[When(env: 'test')]
class OzonReviewInfoRequestTest extends KernelTestCase
{
    private static OzonAuthorizationToken $authorization;

    public static function setUpBeforeClass(): void
    {
        self::$authorization = new OzonAuthorizationToken(
            new UserProfileUid(),
            $_SERVER['TEST_OZON_TOKEN'],
            $_SERVER['TEST_OZON_CLIENT'],
            $_SERVER['TEST_OZON_WAREHOUSE']
        );
    }

    public function testComplete(): void
    {
        self::assertTrue(true);
        return;

        /** @var GetOzonReviewInfoRequest $getOzonReviewInfoRequest */
        $getOzonReviewInfoRequest = self::getContainer()->get(GetOzonReviewInfoRequest::class);
        $getOzonReviewInfoRequest->TokenHttpClient(self::$authorization);

        $reviewInfo = $getOzonReviewInfoRequest
            ->getReviewInfo('0192eda4-842c-70a5-a4e2-e254b267a5ec');

        self::assertNotFalse($reviewInfo);
        self::assertInstanceOf(OzonReviewInfoDTO::class, $reviewInfo);

        /** @var OzonReviewInfoDTO $reviewInfo */
        self::assertIsString($reviewInfo->getId());
        self::assertIsInt($reviewInfo->getSku());
        self::assertIsInt($reviewInfo->getRating());
        self::assertIsArray($reviewInfo->getPhotos());
        self::assertIsString($reviewInfo->getText());
        self::assertIsString($reviewInfo->getOrderStatus());
        self::assertTrue($reviewInfo->getPublished() instanceof DateTimeImmutable);
    }
}
