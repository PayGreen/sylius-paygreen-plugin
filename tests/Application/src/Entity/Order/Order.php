<?php

declare(strict_types=1);

namespace Tests\Paygreen\SyliusPaygreenPlugin\App\Entity\Order;

use Doctrine\ORM\Mapping as ORM;
use Paygreen\SyliusPaygreenPlugin\Entity\MealVoucherableInterface;
use Paygreen\SyliusPaygreenPlugin\Entity\MealVoucherableTrait;
use Sylius\Component\Core\Model\Order as BaseOrder;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_order")
 */
class Order extends BaseOrder implements MealVoucherableInterface
{
    use MealVoucherableTrait;

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

        if ($amount > 0) {
            $amount += $this->getShippingTotal();
        }

        return $amount;
    }
}