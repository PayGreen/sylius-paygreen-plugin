<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Form\Type;

use Paygreen\Sdk\Payment\V2\Enum\PaymentTypeEnum;
use Paygreen\SyliusPaygreenPlugin\Payum\Bridge\PaygreenBridge;
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
        $builder
            ->add('public_key', TextType::class, ['label' => 'paygreen.sylius_plugin.form.public_key'])
            ->add('private_key', TextType::class, ['label' => 'paygreen.sylius_plugin.form.private_key'])
            ->add('payment_type', ChoiceType::class, [
                'label' => 'paygreen.sylius_plugin.form.payment_type.name',
                'choices' => [
                    'paygreen.sylius_plugin.form.payment_type.cb' => PaymentTypeEnum::CB,
                    'paygreen.sylius_plugin.form.payment_type.trd' => PaymentTypeEnum::TRD,
                ],
                'constraints' => [
                    new NotBlank(),
                    new NotNull(),
                ]
            ])
            ->add('display_mode', ChoiceType::class, [
                'label' => 'paygreen.sylius_plugin.form.display_mode.name',
                'choices' => [
                    'paygreen.sylius_plugin.form.display_mode.redirect' => PaygreenBridge::DISPLAY_MODE_REDIRECT,
                    'paygreen.sylius_plugin.form.display_mode.insite' => PaygreenBridge::DISPLAY_MODE_INSITE,
                ],
                'constraints' => [
                    new NotBlank(),
                    new NotNull(),
                ]
            ]);
    }
}