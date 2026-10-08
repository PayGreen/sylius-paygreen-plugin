<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Form\Type;

use GuzzleHttp\Psr7\Response;
use Paygreen\Sdk\Payment\V3\Environment;
use PayGreen\SyliusPayumPlugin\Bridge\PayGreen\ClientFactory;
use PayGreen\SyliusPayumPlugin\Form\Type\PayGreenGatewayConfigurationType;
use PayGreen\SyliusPayumPlugin\Tests\Double\FakeHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class PayGreenGatewayConfigurationTypeTest extends TypeTestCase
{
    use ValidatorExtensionTrait;

    /**
     * @return iterable<string, array{string}>
     */
    public static function secretFieldProvider(): iterable
    {
        yield 'secret key' => ['secret_key'];
    }

    #[DataProvider('secretFieldProvider')]
    public function testSecretFieldsPreserveStoredValueOnEdit(string $fieldName): void
    {
        $form = $this->factory->create(PayGreenGatewayConfigurationType::class, [
            'shop_id' => 'sh_123',
            'public_key' => 'pk_123',
            'secret_key' => 'sk_123',
            'environment_mode' => 'SANDBOX',
        ]);

        $field = $form->get($fieldName);

        self::assertInstanceOf(PasswordType::class, $field->getConfig()->getType()->getInnerType());
        self::assertFalse($field->getConfig()->getOption('always_empty'));
        self::assertSame($form->getData()[$fieldName], $field->getViewData());
    }

    #[DataProvider('secretFieldProvider')]
    public function testSecretFieldsAreNotRequiredOnEveryEdit(string $fieldName): void
    {
        $form = $this->factory->create(PayGreenGatewayConfigurationType::class);
        $constraints = $form->get($fieldName)->getConfig()->getOption('constraints');

        self::assertIsArray($constraints);
        foreach ($constraints as $constraint) {
            self::assertNotInstanceOf(NotBlank::class, $constraint);
        }
    }

    public function testWebhookUrlIsNotDisplayedInAdminConfiguration(): void
    {
        $form = $this->factory->create(PayGreenGatewayConfigurationType::class);

        self::assertFalse($form->has('webhook_url'));
    }

    public function testCredentialsValidationAcceptsValidCredentials(): void
    {
        $violations = $this->validateGatewayConfig([
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_123',
            'environment_mode' => Environment::ENVIRONMENT_SANDBOX,
        ], [
            new Response(200, [], $this->json(['data' => ['token' => 'jwt_123']])),
        ]);

        self::assertCount(0, $violations);
    }

    public function testCredentialsValidationRejectsInvalidCredentials(): void
    {
        $violations = $this->validateGatewayConfig([
            'shop_id' => 'sh_123',
            'secret_key' => 'sk_wrong',
            'environment_mode' => Environment::ENVIRONMENT_PRODUCTION,
        ], [
            new Response(200, [], $this->json(['message' => 'Invalid API key.'])),
        ]);

        self::assertCount(1, $violations);
        self::assertSame('paygreen.gateway_configuration.credentials_invalid', $violations[0]->getMessage());
        self::assertSame('[secret_key]', $violations[0]->getPropertyPath());
    }

    public function testCredentialsValidationRejectsEmptySecretKey(): void
    {
        $violations = $this->validateGatewayConfig([
            'shop_id' => 'sh_123',
            'secret_key' => '',
            'environment_mode' => Environment::ENVIRONMENT_SANDBOX,
        ], []);

        self::assertCount(1, $violations);
        self::assertSame('paygreen.gateway_configuration.secret_key_required', $violations[0]->getMessage());
        self::assertSame('[secret_key]', $violations[0]->getPropertyPath());
    }

    /**
     * @param array<string, mixed> $config
     * @param list<ResponseInterface> $responses
     */
    private function validateGatewayConfig(array $config, array $responses): ConstraintViolationListInterface
    {
        $formType = new PayGreenGatewayConfigurationType(new ClientFactory(
            new FakeHttpClient($responses),
        ));

        return Validation::createValidator()->validate(
            $config,
            new Callback([$formType, 'validateCredentials'], groups: ['sylius']),
            ['sylius'],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
