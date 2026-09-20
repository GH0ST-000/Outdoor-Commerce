<?php

declare(strict_types=1);

/**
 * One-off scaffold helper for Day 11 pricing domain artifacts.
 * Run: php scripts/scaffold_pricing_domain.php
 */
$root = dirname(__DIR__);

$writes = [];

$writes['app/Domains/Pricing/Events/PriceListActivated.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PriceListActivated
{
    public function __construct(public int $priceListId) {}
}
PHP;

$writes['app/Domains/Pricing/Events/PricePublished.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PricePublished
{
    public function __construct(public int $pricePeriodId, public int $variantPriceId) {}
}
PHP;

$writes['app/Domains/Pricing/Events/PriceCancelled.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PriceCancelled
{
    public function __construct(public int $pricePeriodId, public int $variantPriceId) {}
}
PHP;

$writes['app/Domains/Pricing/Events/PriceChanged.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PriceChanged
{
    public function __construct(public int $variantPriceId, public int $priceListId, public int $variantId) {}
}
PHP;

$writes['app/Domains/Pricing/Events/PromotionActivated.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PromotionActivated
{
    public function __construct(public int $promotionId) {}
}
PHP;

$writes['app/Domains/Pricing/Events/PromotionPaused.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PromotionPaused
{
    public function __construct(public int $promotionId) {}
}
PHP;

$writes['app/Domains/Pricing/Events/PromotionEnded.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PromotionEnded
{
    public function __construct(public int $promotionId) {}
}
PHP;

$writes['app/Domains/Pricing/Events/PromotionTargetsChanged.php'] = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Pricing\Events;

final readonly class PromotionTargetsChanged
{
    public function __construct(public int $promotionId) {}
}
PHP;

foreach ($writes as $relative => $contents) {
    $path = $root.'/'.$relative;
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $contents);
}

echo 'Scaffolded '.count($writes)." files\n";
