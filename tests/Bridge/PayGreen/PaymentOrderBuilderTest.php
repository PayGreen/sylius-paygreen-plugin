<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Bridge\PayGreen;

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Paygreen\Sdk\Payment\V3\Enum\DomainEnum;
use Paygreen\Sdk\Payment\V3\Model\PaymentOrder;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\MealVoucherEligibilityCalculator;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\PaymentOrderBuilder;
use PayGreen\SyliusPayumPlugin\Entity\MealVoucherAwareInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class PaymentOrderBuilderTest extends TestCase
{
    public function testItBuildsAPayGreenPaymentOrderFromSyliusPayment(): void
    {
        $billingAddress = $this->createAddress('Romain', 'Da Costa');
        $shippingAddress = $this->createAddress('Romain', 'Da Costa');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn('romain@example.com');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getShippingAddress')->willReturn($shippingAddress);
        $order->method('getCurrencyCode')->willReturn('USD');
        $order->method('getNumber')->willReturn('ORDER-123');
        $order->method('getCustomer')->willReturn($customer);

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(7);
        $payment->method('getAmount')->willReturn(4500);
        $payment->method('getCurrencyCode')->willReturn('USD');
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn([]);

        $paymentOrder = (new PaymentOrderBuilder())->build(
            $payment,
            ['shop_id' => 'sh_123'],
            'https://example.com/return',
            'https://example.com/cancel',
        );

        self::assertSame('ORDER-123-payment-7', $paymentOrder->getReference());
        self::assertSame(4500, $paymentOrder->getAmount());
        self::assertSame('usd', $paymentOrder->getCurrency());
        self::assertTrue($paymentOrder->isAutoCapture());
        self::assertSame('sh_123', $paymentOrder->getShopId());
        self::assertSame('https://example.com/return', $paymentOrder->getReturnUrl());
        self::assertSame('https://example.com/cancel', $paymentOrder->getCancelUrl());
        self::assertSame('romain@example.com', $paymentOrder->getBuyer()->getEmail());
        self::assertSame('FR', $paymentOrder->getShippingAddress()->getCountryCode());
    }

    public function testItSetsHostedPaymentReturnAndCancelUrlsOnTheRealSdkPaymentOrder(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORDER-123');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(7);
        $payment->method('getAmount')->willReturn(4500);
        $payment->method('getCurrencyCode')->willReturn('EUR');
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn([]);

        $paymentOrder = (new PaymentOrderBuilder())->build(
            $payment,
            ['shop_id' => 'sh_123'],
            'https://example.com/payment/return',
            'https://example.com/payment/cancel',
        );

        self::assertInstanceOf(PaymentOrder::class, $paymentOrder);
        self::assertSame('https://example.com/payment/return', $paymentOrder->getReturnUrl());
        self::assertSame('https://example.com/payment/cancel', $paymentOrder->getCancelUrl());
    }

    public function testItFailsWhenARequiredSdkSetterDoesNotExist(): void
    {
        $callRequiredSetter = Closure::bind(
            static function (PaymentOrderBuilder $builder, object $object, string $method, mixed $value): void {
                $builder->callRequiredSetter($object, $method, $value);
            },
            null,
            PaymentOrderBuilder::class,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The installed PayGreen PHP SDK does not expose stdClass::setReturnUrl(), which is required to configure hosted payment return URLs. Please install paygreen/paygreen-php 1.4.0 or newer.');

        $callRequiredSetter(new PaymentOrderBuilder(), new \stdClass(), 'setReturnUrl', 'https://example.com/payment/return');
    }

    public function testItMakesRetryReferencesUniqueAfterCancelledHostedPageReturn(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORDER-123');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(7);
        $payment->method('getAmount')->willReturn(4500);
        $payment->method('getCurrencyCode')->willReturn('EUR');
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn(['paygreen_retry_count' => 2]);

        $paymentOrder = (new PaymentOrderBuilder())->build($payment, ['shop_id' => 'sh_123'], null, null);

        self::assertSame('ORDER-123-payment-7-retry-2', $paymentOrder->getReference());
    }

    public function testItRejectsPaymentOrderReferenceWithoutPersistedPaymentId(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORDER-123');

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(null);
        $payment->method('getOrder')->willReturn($order);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PayGreen payment order reference requires a persisted Sylius payment id.');

        (new PaymentOrderBuilder())->build($payment, ['shop_id' => 'sh_123'], null, null);
    }

    public function testItAddsMealVoucherEligibleAmountsWhenOrderContainsCompatibleProducts(): void
    {
        $variant = $this->createMockForIntersectionOfInterfaces([
            ProductVariantInterface::class,
            MealVoucherAwareInterface::class,
        ]);
        $variant->method('isMealVoucherCompatible')->willReturn(true);

        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getVariant')->willReturn($variant);
        $item->method('getTotal')->willReturn(3200);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getNumber')->willReturn('ORDER-123');
        $order->method('getItems')->willReturn(new ArrayCollection([$item]));

        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getId')->willReturn(7);
        $payment->method('getAmount')->willReturn(4500);
        $payment->method('getCurrencyCode')->willReturn('EUR');
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getDetails')->willReturn([]);

        $paymentOrder = (new PaymentOrderBuilder(new MealVoucherEligibilityCalculator()))
            ->build($payment, ['shop_id' => 'sh_123'], null, null)
        ;

        self::assertSame([
            DomainEnum::FOOD => 3200,
        ], $paymentOrder->getEligibleAmounts());
    }

    private function createAddress(string $firstName, string $lastName): AddressInterface
    {
        $address = $this->createMock(AddressInterface::class);
        $address->method('getFirstName')->willReturn($firstName);
        $address->method('getLastName')->willReturn($lastName);
        $address->method('getStreet')->willReturn('3 route de bosville');
        $address->method('getCity')->willReturn('Cany-Barville');
        $address->method('getCountryCode')->willReturn('FR');
        $address->method('getPostcode')->willReturn('76450');
        $address->method('getProvinceName')->willReturn(null);

        return $address;
    }
}
