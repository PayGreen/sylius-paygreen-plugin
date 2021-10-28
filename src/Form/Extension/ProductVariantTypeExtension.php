<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Form\Extension;

use Sylius\Bundle\ProductBundle\Form\Type\ProductVariantType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductVariantTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mealVoucherCompatible', CheckboxType::class, [
                'label' => 'paygreen.sylius_plugin.form.meal_voucher_compatible',
                'required' => true,
            ])
        ;
    }

    public static function getExtendedTypes(): array
    {
        return [
            ProductVariantType::class,
        ];
    }
}