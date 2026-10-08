<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Bridge\PayGreen;

use Paygreen\Sdk\Payment\V3\Enum\DomainEnum;
use Paygreen\Sdk\Payment\V3\Model\Address;
use Paygreen\Sdk\Payment\V3\Model\Buyer;
use Paygreen\Sdk\Payment\V3\Model\PaymentOrder;
use RuntimeException;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;

final class PaymentOrderBuilder
{
    public function __construct(
        private readonly ?MealVoucherEligibilityCalculatorInterface $mealVoucherEligibilityCalculator = null,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function build(PaymentInterface $payment, array $config, ?string $returnUrl, ?string $cancelUrl): PaymentOrder
    {
        $order = $payment->getOrder();
        $billingAddress = $order instanceof OrderInterface ? $order->getBillingAddress() : null;
        $shippingAddress = $order instanceof OrderInterface ? $order->getShippingAddress() : null;

        $paymentOrder = new PaymentOrder();
        $paymentOrder->setReference($this->resolveReference($payment, $order));
        $paymentOrder->setAmount((int) $payment->getAmount());
        $paymentOrder->setCurrency(strtolower((string) ($payment->getCurrencyCode() ?: $order?->getCurrencyCode())));
        $paymentOrder->setAutoCapture(true);
        $paymentOrder->setDescription($this->resolveDescription($order));
        $paymentOrder->setShopId((string) ($config['shop_id'] ?? ''));

        if (null !== $billingAddress) {
            $paymentOrder->setBuyer($this->buildBuyer($order, $billingAddress));
        }

        if (null !== $shippingAddress) {
            $paymentOrder->setShippingAddress($this->buildAddress($shippingAddress));
        }

        if ($order instanceof OrderInterface) {
            $this->addMealVoucherEligibleAmounts($paymentOrder, $order);
        }

        $this->callRequiredSetter($paymentOrder, 'setReturnUrl', $returnUrl);
        $this->callRequiredSetter($paymentOrder, 'setCancelUrl', $cancelUrl);

        return $paymentOrder;
    }

    private function buildBuyer(?OrderInterface $order, AddressInterface $billingAddress): Buyer
    {
        $customer = $order?->getCustomer();

        $buyer = new Buyer();
        $buyer->setReference((string) ($customer?->getId() ?? $order?->getNumber() ?? $order?->getId() ?? 'guest'));
        $buyer->setEmail((string) ($customer?->getEmail() ?? $this->callOptionalGetter($order, 'getCustomerEmail') ?? ''));
        $buyer->setFirstName((string) $billingAddress->getFirstName());
        $buyer->setLastName((string) $billingAddress->getLastName());
        $buyer->setBillingAddress($this->buildAddress($billingAddress));

        return $buyer;
    }

    private function buildAddress(AddressInterface $address): Address
    {
        $payGreenAddress = new Address();
        $payGreenAddress->setStreetLineOne(trim((string) $address->getStreet()));
        $payGreenAddress->setCity((string) $address->getCity());
        $payGreenAddress->setCountryCode(strtoupper((string) $address->getCountryCode()));
        $payGreenAddress->setPostalCode(substr((string) $address->getPostcode(), 0, 10));

        $this->callOptionalSetter($payGreenAddress, 'setStreetLineTwo', $address->getProvinceName());

        return $payGreenAddress;
    }

    private function resolveReference(PaymentInterface $payment, ?OrderInterface $order): string
    {
        $orderReference = (string) ($order?->getNumber() ?? $order?->getId() ?? 'order');
        $paymentId = $payment->getId();
        if (!is_int($paymentId) && !(is_string($paymentId) && ctype_digit($paymentId))) {
            throw new RuntimeException('PayGreen payment order reference requires a persisted Sylius payment id.');
        }

        $details = $payment->getDetails() ?? [];
        $retryCount = (int) ($details['paygreen_retry_count'] ?? 0);
        $retrySuffix = $retryCount > 0 ? sprintf('-retry-%d', $retryCount) : '';

        return sprintf('%s-payment-%s%s', $orderReference, (string) $paymentId, $retrySuffix);
    }

    private function resolveDescription(?OrderInterface $order): string
    {
        if (null === $order) {
            return 'Sylius order payment';
        }

        return sprintf('Sylius order %s', (string) ($order->getNumber() ?? $order->getId()));
    }

    private function addMealVoucherEligibleAmounts(PaymentOrder $paymentOrder, OrderInterface $order): void
    {
        if (null === $this->mealVoucherEligibilityCalculator) {
            return;
        }

        $eligibleAmount = $this->mealVoucherEligibilityCalculator->calculate($order);
        if ($eligibleAmount <= 0) {
            return;
        }

        // PayGreen V3 keys eligible_amounts by domain (ecommerce|travel|food),
        // not by platform. Meal vouchers belong to the "food" domain.
        $paymentOrder->setEligibleAmounts([DomainEnum::FOOD => $eligibleAmount]);
    }

    private function callOptionalSetter(object $object, string $method, mixed $value): void
    {
        if (null === $value || '' === $value || !method_exists($object, $method)) {
            return;
        }

        $object->{$method}($value);
    }

    private function callRequiredSetter(object $object, string $method, mixed $value): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!method_exists($object, $method)) {
            throw new RuntimeException(sprintf(
                'The installed PayGreen PHP SDK does not expose %s::%s(), which is required to configure hosted payment return URLs. Please install paygreen/paygreen-php 1.4.0 or newer.',
                $object::class,
                $method,
            ));
        }

        $object->{$method}($value);
    }

    private function callOptionalGetter(?object $object, string $method): mixed
    {
        if (null === $object || !method_exists($object, $method)) {
            return null;
        }

        return $object->{$method}();
    }
}
