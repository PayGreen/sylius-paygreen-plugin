<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;

trait MealVoucherAwareTrait
{
    /**
     * @var bool
     * @ORM\Column(name="meal_voucher_compatible", type="boolean", nullable=false, options={"default"=true})
     */
    protected $mealVoucherCompatible = true;

    /**
     * @return bool
     */
    public function isMealVoucherCompatible(): bool
    {
        return $this->mealVoucherCompatible;
    }

    /**
     * @param bool $mealVoucherCompatible
     */
    public function setMealVoucherCompatible(bool $mealVoucherCompatible): void
    {
        $this->mealVoucherCompatible = $mealVoucherCompatible;
    }

}