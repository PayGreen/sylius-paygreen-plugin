<?php

declare(strict_types=1);

namespace Tests\Paygreen\SyliusPaygreenPlugin\App\Entity\Product;

use Doctrine\ORM\Mapping as ORM;
use Paygreen\SyliusPaygreenPlugin\Entity\MealVoucherAwareInterface;
use Paygreen\SyliusPaygreenPlugin\Entity\MealVoucherAwareTrait;
use Sylius\Component\Core\Model\ProductVariant as BaseProductVariant;

/**
 * @ORM\Entity
 * @ORM\Table(name="sylius_product_variant")
 */
class ProductVariant extends BaseProductVariant implements MealVoucherAwareInterface
{
    use MealVoucherAwareTrait;
}