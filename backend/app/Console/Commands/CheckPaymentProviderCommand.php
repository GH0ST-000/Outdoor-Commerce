<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Payments\Actions\CheckPaymentProviderAction;
use Illuminate\Console\Command;

final class CheckPaymentProviderCommand extends Command
{
    protected $signature = 'payments:check-provider {code : Provider code such as bog} {--connect : Authenticate once without creating a payment}';

    protected $description = 'Validate payment-provider configuration without printing secrets.';

    public function handle(CheckPaymentProviderAction $action): int
    {
        $code = (string) $this->argument('code');
        $result = $action->execute($code, (bool) $this->option('connect'));

        $this->info('provider: '.$result['provider']);
        $this->info('enabled: '.($result['enabled'] ? 'yes' : 'no'));
        $this->info('ready: '.($result['ready'] ? 'yes' : 'no'));
        if ($result['issues'] !== []) {
            $this->warn('issues: '.implode(', ', $result['issues']));
        }
        if ($result['connected'] !== null) {
            $this->info('connected: '.($result['connected'] ? 'yes' : 'no'));
        }

        if (! $result['enabled']) {
            return self::SUCCESS;
        }

        return $result['ready'] && ($result['connected'] ?? true) ? self::SUCCESS : self::FAILURE;
    }
}
