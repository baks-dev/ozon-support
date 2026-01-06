<?php
/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Ozon\Support\Messenger\Orders;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Twig\CallTwigFuncExtension;
use BaksDev\Orders\Order\Entity\Event\OrderEvent;
use BaksDev\Orders\Order\Messenger\OrderMessage;
use BaksDev\Orders\Order\Repository\OrderEvent\OrderEventInterface;
use BaksDev\Orders\Order\Type\Status\OrderStatus\Collection\OrderStatusNew;
use BaksDev\Ozon\Orders\Type\DeliveryType\TypeDeliveryFbsOzon;
use BaksDev\Ozon\Support\Api\Post\CreateChat\CreateOzonChatRequest;
use BaksDev\Ozon\Support\Type\OzonSupportProfileType;
use BaksDev\Ozon\Type\Id\OzonTokenUid;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByEventInterface;
use BaksDev\Products\Product\Repository\ProductDetail\ProductDetailByEventResult;
use BaksDev\Support\Entity\Support;
use BaksDev\Support\Type\Priority\SupportPriority;
use BaksDev\Support\Type\Priority\SupportPriority\Collection\SupportPriorityLow;
use BaksDev\Support\Type\Status\SupportStatus;
use BaksDev\Support\Type\Status\SupportStatus\Collection\SupportStatusClose;
use BaksDev\Support\UseCase\Admin\New\Invariable\SupportInvariableDTO;
use BaksDev\Support\UseCase\Admin\New\Message\SupportMessageDTO;
use BaksDev\Support\UseCase\Admin\New\SupportDTO;
use BaksDev\Support\UseCase\Admin\New\SupportHandler;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twig\Environment;

#[AsMessageHandler(priority: -100)]
final readonly class CreateOzonChatMessageByNewOrderDispatcher
{
    public function __construct(
        private OrderEventInterface $orderEventRepository,
        #[Target('ozonSupportLogger')] private LoggerInterface $logger,
        private DeduplicatorInterface $deduplicator,
        private CreateOzonChatRequest $CreateOzonChatRequest,
        private SupportHandler $SupportHandler,
        private ProductDetailByEventInterface $ProductDetailByEventRepository,
        private Environment $environment

    ) {}

    public function __invoke(OrderMessage $message): void
    {
        /** Не отправляем сообщение дважды */
        $Deduplicator = $this->deduplicator
            ->namespace('orders-order')
            ->deduplication([
                (string) $message->getId(),
                self::class,
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $OrderEvent = $this->orderEventRepository
            ->find($message->getEvent());

        if(false === ($OrderEvent instanceof OrderEvent))
        {
            return;
        }

        /** Если статус не New «Новый»  */
        if(false === $OrderEvent->isStatusEquals(OrderStatusNew::class))
        {
            return;
        }

        if(false === $OrderEvent->isDeliveryTypeEquals(TypeDeliveryFbsOzon::TYPE))
        {
            $Deduplicator->save();
            return;
        }

        if(is_null($OrderEvent->getOrderTokenIdentifier()))
        {
            return;
        }

        /**
         * Дедубликатор по номеру заказа
         */

        $number = $OrderEvent->getOrderNumber();

        $pos = strrpos($OrderEvent->getOrderNumber(), '-'); // находим позицию последнего тире

        if($pos !== false)
        {
            $number = substr($number, 0, $pos);
        }

        $DeduplicatorNumber = $this->deduplicator
            ->namespace('orders-order')
            ->deduplication([
                $number,
                self::class,
            ]);

        if($DeduplicatorNumber->isExecuted())
        {
            return;
        }

        /**
         * Создаем чат с пользователем
         */

        $OzonTokenUid = new OzonTokenUid($OrderEvent->getOrderTokenIdentifier());

        $chatId = $this->CreateOzonChatRequest
            ->forTokenIdentifier($OzonTokenUid)
            ->order($OrderEvent->getOrderNumber())
            ->create();

        if(is_bool($chatId))
        {
            return;
        }

        /** Создаем тикет с сообщением */
        $SupportDTO = new SupportDTO()
            ->setPriority(new SupportPriority(SupportPriorityLow::class)) // CustomerMessage - низкий приоритет
            ->setStatus(new SupportStatus(SupportStatusClose::class)); // Для нового чата с сообщением о заказе- StatusClose

        /** Присваиваем токен для последующего поиска */
        $SupportDTO->getToken()->setValue($OzonTokenUid);

        /** SupportInvariable */
        $SupportInvariableDTO = new SupportInvariableDTO()
            ->setProfile($OrderEvent->getOrderProfile())
            ->setType(new TypeProfileUid(OzonSupportProfileType::TYPE))
            ->setTicket($chatId)
            ->setTitle(sprintf('Заказ #%s', $number));

        $SupportDTO->setInvariable($SupportInvariableDTO);


        /** Создаем сообщение клиенту */

        $call = $this->environment->getExtension(CallTwigFuncExtension::class);

        $msg = sprintf(
            'Здравствуйте! Спасибо за Ваш заказ #%s.',
            $number,
        );

        $msg .= PHP_EOL.'Настоятельно рекомендуем Вам проверить, соответствуют ли характеристики товара Вашим требованиям:';

        foreach($OrderEvent->getProduct() as $OrderProduct)
        {
            /** Получаем информацию о продукте */

            $ProductDetailByEventResult = $this->ProductDetailByEventRepository
                ->event($OrderProduct->getProduct())
                ->offer($OrderProduct->getOffer())
                ->variation($OrderProduct->getVariation())
                ->modification($OrderProduct->getModification())
                ->findResult();

            if(false === ($ProductDetailByEventResult instanceof ProductDetailByEventResult))
            {
                $this->logger->critical('ozon-support: Ошибка при получении детальной информации о продукте', [
                    self::class.':'.__LINE__,
                ]);

                continue;
            }

            /** Формируем название продукта */

            $name = $ProductDetailByEventResult->getProductName();

            /**
             * Множественный вариант
             */

            $variation = $call->call(
                $this->environment,
                $ProductDetailByEventResult->getProductVariationValue(),
                $ProductDetailByEventResult->getProductVariationReference().'_render',
            );

            $name .= $variation ? ' '.trim($variation) : '';


            /**
             * Модификация множественного варианта
             */

            $modification = $call->call(
                $this->environment,
                $ProductDetailByEventResult->getProductModificationValue(),
                $ProductDetailByEventResult->getProductModificationReference().'_render',
            );


            $name .= $modification ? ' '.trim($modification) : '';


            /**
             * Торговое предложение
             */

            $offer = $call->call(
                $this->environment,
                $ProductDetailByEventResult->getProductOfferValue(),
                $ProductDetailByEventResult->getProductOfferReference().'_render',
            );

            $name .= $offer ? ' '.trim($offer) : '';

            $name .= $ProductDetailByEventResult->getProductOfferPostfix() ? ' '.$ProductDetailByEventResult->getProductOfferPostfix() : '';
            $name .= $ProductDetailByEventResult->getProductVariationPostfix() ? ' '.$ProductDetailByEventResult->getProductVariationPostfix() : '';
            $name .= $ProductDetailByEventResult->getProductModificationPostfix() ? ' '.$ProductDetailByEventResult->getProductModificationPostfix() : '';

            $msg .= PHP_EOL.$name;

            /** Перечисляем характеристики */

            $characteristic = null;

            if($ProductDetailByEventResult->getProductVariationValue())
            {
                $characteristic[] = $ProductDetailByEventResult->getProductVariationName().': '.$ProductDetailByEventResult->getProductVariationValue();
            }

            if($ProductDetailByEventResult->getProductModificationValue())
            {
                $characteristic[] = $ProductDetailByEventResult->getProductModificationName().': '.$ProductDetailByEventResult->getProductModificationValue();
            }

            if($ProductDetailByEventResult->getProductOfferValue())
            {
                $characteristic[] = $ProductDetailByEventResult->getProductOfferName().': '.$ProductDetailByEventResult->getProductOfferValue();
            }

            if(false === empty($characteristic))
            {
                $msg .= PHP_EOL.implode(', ', $characteristic);
            }
        }

        $msg .= PHP_EOL.'Обращаем Ваше внимание, что возврат товара будет возможен только в случае возникновения гарантийного случая или после предварительного согласования условий.';

        $msg .= PHP_EOL.'Для уверенности в Вашем выборе наша команда готова предоставить всю необходимую информацию о продукте и его сертификации. Мы оперативно ответим на все Ваши вопросы.';

        $msg .= PHP_EOL.'Спасибо что выбрали наш магазин для покупки!';

        $supportMessageDTO = new SupportMessageDTO()
            ->setName('auto (Bot Seller)')
            ->setMessage($msg)
            ->setDate(new DateTimeImmutable('now'))
            ->setOutMessage();

        $SupportDTO->addMessage($supportMessageDTO);

        $handle = $this->SupportHandler->handle($SupportDTO);

        if(false === $handle instanceof Support)
        {
            $this->logger->critical(
                sprintf('ozon-support: Ошибка %s при создании чата поддержки', $handle),
                [
                    self::class.':'.__LINE__,
                    var_export($message, true),
                    $SupportDTO->getInvariable()?->getTicket(),
                ],
            );

            return;
        }

        $DeduplicatorNumber->save();

        $Deduplicator->save();
    }
}
