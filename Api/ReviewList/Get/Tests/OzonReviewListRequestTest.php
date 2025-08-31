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

namespace BaksDev\Ozon\Support\Api\ReviewList\Get\Tests;

use BaksDev\Ozon\Orders\Type\ProfileType\TypeProfileFbsOzon;
use BaksDev\Ozon\Support\Api\ReviewList\Get\OzonReviewDTO;
use BaksDev\Ozon\Support\Api\ReviewList\Get\OzonReviewListRequest;
use BaksDev\Ozon\Type\Authorization\OzonAuthorizationToken;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[Group('ozon-support')]
#[When(env: 'test')]
class OzonReviewListRequestTest extends KernelTestCase
{
    private static OzonAuthorizationToken $authorization;

    public static function setUpBeforeClass(): void
    {
        self::$Authorization = new OzonAuthorizationToken(
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
        /** @var OzonReviewListRequest $ozonReviewListRequest */
        $ozonReviewListRequest = self::getContainer()->get(OzonReviewListRequest::class);
        $ozonReviewListRequest->TokenHttpClient(self::$authorization);

        $reviewList = $ozonReviewListRequest
            ->status(OzonReviewListRequest::STATUS_UNPROCESSED)
            ->sort(OzonReviewListRequest::SORT_DESC)
            ->getReviewList();

        if(false === $reviewList || false === $reviewList->valid())
        {
            self::assertFalse(false);
            return;
        }

        self::assertNotFalse($reviewList);

        /** @var OzonReviewDTO $review */
        foreach($reviewList as $review)
        {
            self::assertInstanceOf(OzonReviewDTO::class, $review);

            self::assertIsString($review->getId());
            self::assertIsInt($review->getSku());
            self::assertIsString($review->getText());
            self::assertTrue($review->getPublished() instanceof DateTimeImmutable);
            self::assertIsInt($review->getRating());
            self::assertIsString($review->getStatus());
            self::assertIsInt($review->getCommentsAmount());
            self::assertIsInt($review->getPhotosAmount());
            self::assertIsInt($review->getVideosAmount());
            self::assertIsString($review->getOrderStatus());
            self::assertIsBool($review->isRatingPart());
        }
    }
}
