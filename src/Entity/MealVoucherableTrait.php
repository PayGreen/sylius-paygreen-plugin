<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\ProductVariantInterface;

trait MealVoucherableTrait
{
    protected $items;

    public function getMealVoucherCompatibleAmount(): int
    {
        $amount = 0;

        foreach ($this->items as $item) {
            /** @var $variant ProductVariantInterface */
            $variant = $item->getVariant();

            if ($variant->isMealVoucherCompatible()) {
                $amount += $item->getTotal();
            }
        }

        return $amount;
    }

}