<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Form\Extension;

use PayGreen\SyliusPayumPlugin\Entity\MealVoucherAwareInterface;
use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductVariantTypeExtension extends AbstractTypeExtension
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dataClass = $options['data_class'] ?? null;
        if (!is_string($dataClass) || !is_a($dataClass, MealVoucherAwareInterface::class, true)) {
            return;
        }

        $builder->add('mealVoucherCompatible', CheckboxType::class, [
            'required' => false,
            'label' => 'paygreen.product_variant.meal_voucher_compatible',
        ]);
    }

    /**
     * @return iterable<class-string>
     */
    public static function getExtendedTypes(): iterable
    {
        return [ProductVariantType::class];
    }
}
