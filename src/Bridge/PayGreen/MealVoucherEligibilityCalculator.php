<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Bridge\PayGreen;

use PayGreen\SyliusPayumPlugin\Entity\MealVoucherAwareInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class MealVoucherEligibilityCalculator implements MealVoucherEligibilityCalculatorInterface
{
    public function calculate(OrderInterface $order): int
    {
        $eligibleAmount = 0;

        foreach ($order->getItems() as $item) {
            if (!$item instanceof OrderItemInterface) {
                continue;
            }

            $variant = $item->getVariant();
            if (!$variant instanceof MealVoucherAwareInterface || !$variant->isMealVoucherCompatible()) {
                continue;
            }

            $eligibleAmount += $item->getTotal();
        }

        return max(0, $eligibleAmount);
    }
}
