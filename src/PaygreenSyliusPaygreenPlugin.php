<?php

declare(strict_types=1);

namespace Paygreen\SyliusPaygreenPlugin;

use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PaygreenSyliusPaygreenPlugin extends Bundle
{
    use SyliusPluginTrait;
}
