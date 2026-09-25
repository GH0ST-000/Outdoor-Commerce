<?php

declare(strict_types=1);

namespace App\Domains\Legal\Enums;

enum LegalRuleEffect: string
{
    case Allow = 'allow';
    case Prohibit = 'prohibit';
    case Condition = 'condition';
    case Limit = 'limit';
    case Require = 'require';
}
