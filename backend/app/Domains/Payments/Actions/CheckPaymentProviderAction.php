<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Enums\PaymentProviderCode;
use App\Domains\Payments\Providers\BankOfGeorgia\BankOfGeorgiaConfigurationValidator;
use App\Domains\Payments\Providers\BankOfGeorgia\BankOfGeorgiaTokenProvider;
use App\Domains\Payments\Services\PaymentProviderRegistry;
use Throwable;

final class CheckPaymentProviderAction
{
    public function __construct(
        private readonly PaymentProviderRegistry $registry,
        private readonly BankOfGeorgiaConfigurationValidator $bog,
        private readonly BankOfGeorgiaTokenProvider $tokens,
    ) {}

    /**
     * @return array{provider: string, enabled: bool, ready: bool, issues: list<string>, connected: ?bool}
     */
    public function execute(string $code, bool $connect = false): array
    {
        $health = $this->registry->health($code);
        $issues = [];
        $ready = (bool) ($health['ready'] ?? $health['enabled']);

        if ($code === PaymentProviderCode::Bog->value) {
            $issues = $this->bog->issues();
            $ready = $this->bog->isReady();
        }

        $connected = null;
        if ($connect && $code === PaymentProviderCode::Bog->value && $ready) {
            try {
                $this->tokens->accessToken();
                $connected = true;
            } catch (Throwable) {
                $connected = false;
            }
        }

        return [
            'provider' => $code,
            'enabled' => (bool) $health['enabled'],
            'ready' => $ready,
            'issues' => $issues,
            'connected' => $connected,
        ];
    }
}
