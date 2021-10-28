<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Entity;

interface MealVoucherableInterface
{
    public function getMealVoucherCompatibleAmount(): int;
}