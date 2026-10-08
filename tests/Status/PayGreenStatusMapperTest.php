<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Tests\Status;

use PayGreen\SyliusPayumPlugin\Status\PayGreenStatusMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayGreenStatusMapperTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function testItMapsPayGreenStatusesToInternalStatuses(?string $payGreenStatus, string $expectedStatus): void
    {
        self::assertSame($expectedStatus, (new PayGreenStatusMapper())->map($payGreenStatus));
    }

    /**
     * @return iterable<string, array{0: string|null, 1: string}>
     */
    public static function statusProvider(): iterable
    {
        yield 'empty is new' => [null, PayGreenStatusMapper::STATUS_NEW];
        yield 'pending' => ['pending', PayGreenStatusMapper::STATUS_PENDING];
        yield 'authorized' => ['authorized', PayGreenStatusMapper::STATUS_AUTHORIZED];
        yield 'successed' => ['successed', PayGreenStatusMapper::STATUS_CAPTURED];
        yield 'prefixed successed' => ['payment_order.successed', PayGreenStatusMapper::STATUS_CAPTURED];
        yield 'prefixed captured' => ['transaction.captured', PayGreenStatusMapper::STATUS_CAPTURED];
        yield 'canceled' => ['canceled', PayGreenStatusMapper::STATUS_CANCELED];
        yield 'expired' => ['expired', PayGreenStatusMapper::STATUS_CANCELED];
        yield 'refused' => ['refused', PayGreenStatusMapper::STATUS_FAILED];
        yield 'error' => ['error', PayGreenStatusMapper::STATUS_FAILED];
        yield 'future status' => ['settling', PayGreenStatusMapper::STATUS_UNKNOWN];
    }
}
