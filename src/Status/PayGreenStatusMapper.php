<?php

declare(strict_types=1);

namespace PayGreen\SyliusPayumPlugin\Status;

final class PayGreenStatusMapper
{
    public const STATUS_NEW = 'new';
    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_FAILED = 'failed';
    public const STATUS_UNKNOWN = 'unknown';

    public function map(?string $payGreenStatus): string
    {
        $normalizedStatus = strtolower((string) $payGreenStatus);
        $shortStatus = str_contains($normalizedStatus, '.') ? substr($normalizedStatus, (int) strrpos($normalizedStatus, '.') + 1) : $normalizedStatus;

        return match ($shortStatus) {
            '', 'created' => self::STATUS_NEW,
            'pending' => self::STATUS_PENDING,
            'authorized' => self::STATUS_AUTHORIZED,
            'successed', 'succeeded', 'captured', 'paid' => self::STATUS_CAPTURED,
            'canceled', 'cancelled', 'expired' => self::STATUS_CANCELED,
            'refused', 'error', 'failed' => self::STATUS_FAILED,
            default => self::STATUS_UNKNOWN,
        };
    }
}
