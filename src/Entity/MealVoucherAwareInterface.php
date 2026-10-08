<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Entity;

interface MealVoucherAwareInterface
{
    public function isMealVoucherCompatible(): bool;

    public function setMealVoucherCompatible(bool $mealVoucherCompatible): void;
}
