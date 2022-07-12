<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

final class PaygreenGatewayConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Add public and private to the form
        $builder->add('public_key', TextType::class, ['label' => 'paygreen.sylius_plugin.form.public_key']);
        $builder->add('private_key', TextType::class, ['label' => 'paygreen.sylius_plugin.form.private_key']);
        // Allows the customer to choose the payment type of his button
        $builder->add('payment_type', ChoiceType::class, [
            'label' => 'paygreen.sylius_plugin.form.payment_type.name',
            'choices' => [
                'paygreen.sylius_plugin.form.payment_type.CB' => 'CB',
                'paygreen.sylius_plugin.form.payment_type.TRD' => 'TRD',
                'paygreen.sylius_plugin.form.payment_type.LUNCHR' => 'LUNCHR',
                'paygreen.sylius_plugin.form.payment_type.RESTOFLASH' => 'RESTOFLASH',
            ],
            'constraints' => [
                new NotBlank(),
                new NotNull(),
            ]
        ]);
    }
}