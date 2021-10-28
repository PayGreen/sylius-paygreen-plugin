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
}