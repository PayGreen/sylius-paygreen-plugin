<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Types;


class TransactionStatus
{
    public const STATUS_SUCCEEDED = "SUCCESSED";
    public const STATUS_REFUSED = "REFUSED";
    public const STATUS_CANCELLED = "CANCELLED";
    public const STATUS_PENDING = "PENDING";
    public const STATUS_WAITING = "WAITING";
    public const STATUS_REFUNDED = "REFUNDED";
    public const STATUS_EXPIRED = "EXPIRED";
}
