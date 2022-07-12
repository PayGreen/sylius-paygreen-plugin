<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin\Payum\Enum;

class MealVoucherTypeEnum
{
    const RESTOFLASH = 'RESTOFLASH';
    const SWILE = 'SWILE';
    const TRD = 'TRD';

    /**
     * @return array<string>
     */
    public static function getMealVoucherTypes()
    {
        return [
            self::RESTOFLASH,
            self::SWILE,
            self::TRD,
        ];
    }
}