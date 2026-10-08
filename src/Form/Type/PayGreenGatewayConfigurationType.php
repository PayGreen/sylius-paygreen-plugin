<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Form\Type;

use Paygreen\Sdk\Payment\V3\Environment;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PayGreenGatewayConfigurationType extends AbstractType
{
    public function __construct(
        private readonly ?ClientFactory $clientFactory = null,
        private readonly string $kernelEnvironment = 'prod',
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('shop_id', TextType::class, [
                'label' => 'paygreen.gateway_configuration.shop_id',
                'constraints' => [new NotBlank(['groups' => ['sylius']])],
            ])
            ->add('public_key', TextType::class, [
                'label' => 'paygreen.gateway_configuration.public_key',
            ])
            ->add('secret_key', PasswordType::class, [
                'label' => 'paygreen.gateway_configuration.secret_key',
                'always_empty' => false,
            ])
            ->add('environment_mode', ChoiceType::class, [
                'label' => 'paygreen.gateway_configuration.environment',
                'choices' => $this->buildEnvironmentChoices(),
            ])
        ;
    }

    /**
     * @return array<string, string>
     */
    private function buildEnvironmentChoices(): array
    {
        $choices = [
            'paygreen.gateway_configuration.environment_production' => Environment::ENVIRONMENT_PRODUCTION,
            'paygreen.gateway_configuration.environment_sandbox' => Environment::ENVIRONMENT_SANDBOX,
        ];

        if ('dev' === $this->kernelEnvironment) {
            $choices['paygreen.gateway_configuration.environment_recette'] = Environment::ENVIRONMENT_RECETTE;
        }

        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [
                new Callback([$this, 'validateCredentials'], groups: ['sylius']),
            ],
        ]);
    }

    public function validateCredentials(mixed $config, ExecutionContextInterface $context): void
    {
        if (!is_array($config)) {
            return;
        }

        $shopId = trim((string) ($config['shop_id'] ?? ''));
        $secretKey = trim((string) ($config['secret_key'] ?? ''));
        if ('' === $secretKey) {
            $context
                ->buildViolation('paygreen.gateway_configuration.secret_key_required')
                ->atPath('[secret_key]')
                ->addViolation()
            ;

            return;
        }

        if ('' === $shopId || null === $this->clientFactory) {
            return;
        }

        try {
            $this->clientFactory->create([
                'shop_id' => $shopId,
                'secret_key' => $secretKey,
                'environment' => (string) ($config['environment_mode'] ?? Environment::ENVIRONMENT_PRODUCTION),
            ]);
        } catch (\Throwable) {
            $context
                ->buildViolation('paygreen.gateway_configuration.credentials_invalid')
                ->atPath('[secret_key]')
                ->addViolation()
            ;
        }
    }
}
