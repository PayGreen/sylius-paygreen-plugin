<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Entity;

interface MealVoucherAwareInterface
{
    public function isMealVoucherCompatible(): bool;

    public function setMealVoucherCompatible(bool $mealVoucherCompatible): void;
}