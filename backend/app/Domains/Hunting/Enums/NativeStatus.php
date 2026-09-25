<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Enums;

enum NativeStatus: string
{
    case Native = 'native';
    case Introduced = 'introduced';
    case Vagrant = 'vagrant';
    case Unknown = 'unknown';
}
