<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

trait MealVoucherAwareTrait
{
    #[ORM\Column(name: 'meal_voucher_compatible', type: 'boolean', options: ['default' => false])]
    protected bool $mealVoucherCompatible = false;

    public function isMealVoucherCompatible(): bool
    {
        return $this->mealVoucherCompatible;
    }

    public function setMealVoucherCompatible(bool $mealVoucherCompatible): void
    {
        $this->mealVoucherCompatible = $mealVoucherCompatible;
    }
}
