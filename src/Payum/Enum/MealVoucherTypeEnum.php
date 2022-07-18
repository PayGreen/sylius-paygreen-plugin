<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Enum;

class MealVoucherTypeEnum
{
    const RESTOFLASH = 'RESTOFLASH';
    const LUNCHR = 'LUNCHR';
    const TRD = 'TRD';

    /**
     * @return array<string>
     */
    public static function getMealVoucherTypes()
    {
        return [
            self::RESTOFLASH,
            self::LUNCHR,
            self::TRD,
        ];
    }
}