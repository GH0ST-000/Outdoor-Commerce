<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Identity\Services\RolePermissionSynchronizer;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Console\Command;

final class SynchronizeAccessControlCommand extends Command
{
    protected $signature = 'access-control:sync {--dry-run : Report changes without writing}';

    protected $description = 'Synchronize approved roles and permissions without deleting unknown production roles';

    public function handle(
        RolePermissionSynchronizer $synchronizer,
        RecordAuditEventAction $recordAuditEvent,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $summary = $synchronizer->synchronize($dryRun);

        $this->info($dryRun ? 'Dry-run access-control synchronization summary:' : 'Access-control synchronization complete:');
        $this->line('Permissions created: '.(implode(', ', $summary['permissions_created']) ?: '(none)'));
        $this->line('Roles created: '.(implode(', ', $summary['roles_created']) ?: '(none)'));
        $this->line('Unknown roles preserved: '.(implode(', ', $summary['unknown_roles_preserved']) ?: '(none)'));

        foreach ($summary['role_permissions_synced'] as $role => $permissions) {
            $this->line(sprintf('%s => %d permissions', $role, count($permissions)));
        }

        if (! $dryRun) {
            $recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::PermissionConfigurationSynchronized,
                subjectType: 'access_control',
                subjectId: 'sync',
                metadata: [
                    'permissions_created' => $summary['permissions_created'],
                    'roles_created' => $summary['roles_created'],
                    'unknown_roles_preserved' => $summary['unknown_roles_preserved'],
                ],
            ));
        }

        return self::SUCCESS;
    }
}
