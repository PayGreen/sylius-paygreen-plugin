<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Bridge\PayGreen;

use Sylius\Component\Core\Model\OrderInterface;

interface MealVoucherEligibilityCalculatorInterface
{
    public function calculate(OrderInterface $order): int;
}
