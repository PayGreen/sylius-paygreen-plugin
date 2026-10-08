<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Bridge\PayGreen;

use Doctrine\Common\Collections\ArrayCollection;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\MealVoucherEligibilityCalculator;
use PayGreen\SyliusPayumPlugin\Entity\MealVoucherAwareInterface;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;

final class MealVoucherEligibilityCalculatorTest extends TestCase
{
    public function testItSumsOnlyMealVoucherCompatibleOrderItems(): void
    {
        $eligibleVariant = $this->createMockForIntersectionOfInterfaces([
            ProductVariantInterface::class,
            MealVoucherAwareInterface::class,
        ]);
        $eligibleVariant->method('isMealVoucherCompatible')->willReturn(true);

        $ineligibleVariant = $this->createMockForIntersectionOfInterfaces([
            ProductVariantInterface::class,
            MealVoucherAwareInterface::class,
        ]);
        $ineligibleVariant->method('isMealVoucherCompatible')->willReturn(false);

        $unknownVariant = $this->createMock(ProductVariantInterface::class);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([
            $this->createOrderItem($eligibleVariant, 1500),
            $this->createOrderItem($ineligibleVariant, 900),
            $this->createOrderItem($unknownVariant, 700),
            $this->createOrderItem($eligibleVariant, 250),
        ]));

        self::assertSame(1750, (new MealVoucherEligibilityCalculator())->calculate($order));
    }

    public function testItReturnsZeroWhenNoProductVariantIsCompatible(): void
    {
        $variant = $this->createMockForIntersectionOfInterfaces([
            ProductVariantInterface::class,
            MealVoucherAwareInterface::class,
        ]);
        $variant->method('isMealVoucherCompatible')->willReturn(false);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getItems')->willReturn(new ArrayCollection([
            $this->createOrderItem($variant, 1200),
        ]));

        self::assertSame(0, (new MealVoucherEligibilityCalculator())->calculate($order));
    }

    private function createOrderItem(ProductVariantInterface $variant, int $total): OrderItemInterface
    {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getVariant')->willReturn($variant);
        $item->method('getTotal')->willReturn($total);

        return $item;
    }
}
