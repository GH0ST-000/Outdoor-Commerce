<?php

declare(strict_types=1);

namespace App\Domains\Identity\Actions;

use App\Domains\Identity\DTOs\CreateAdministratorData;
use App\Domains\Identity\Enums\Role;
use App\Domains\Identity\Enums\UserStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\EmailNormalizer;
use App\Domains\Identity\Support\NameNormalizer;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

final class CreateAdministratorAction
{
    public function __construct(
        private readonly RecordAuditEventAction $recordAuditEvent,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @return array{user: User, created: bool, promoted: bool}
     */
    public function execute(
        CreateAdministratorData $data,
        bool $confirmPromoteExisting = false,
        ?int $actorUserId = null,
        ?string $requestId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $email = EmailNormalizer::normalize($data->email);
        $firstName = NameNormalizer::normalize($data->firstName);
        $lastName = NameNormalizer::normalize($data->lastName);

        return DB::transaction(function () use (
            $data,
            $email,
            $firstName,
            $lastName,
            $confirmPromoteExisting,
            $actorUserId,
            $requestId,
            $ipAddress,
            $userAgent,
        ): array {
            /** @var User|null $existing */
            $existing = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($existing !== null) {
                if (! $confirmPromoteExisting) {
                    throw new \InvalidArgumentException(
                        'A user with this email already exists. Confirm promotion to continue.',
                    );
                }

                if ($existing->status !== UserStatus::Active) {
                    throw new \InvalidArgumentException(
                        'Only active users may be promoted to administrator.',
                    );
                }

                $existing->assignRole(Role::Admin->value);
                $this->permissionRegistrar->forgetCachedPermissions();

                $this->recordAuditEvent->execute(new AuditEventData(
                    event: AuditEvent::AdministratorPromoted,
                    actorUserId: $actorUserId,
                    subjectType: 'user',
                    subjectId: (string) $existing->id,
                    requestId: $requestId,
                    ipAddress: $ipAddress,
                    userAgent: $userAgent,
                    newValues: [
                        'roles' => $existing->getRoleNames()->sort()->values()->all(),
                    ],
                    metadata: [
                        'email' => $existing->email,
                    ],
                ));

                return [
                    'user' => $existing->fresh(['roles']) ?? $existing,
                    'created' => false,
                    'promoted' => true,
                ];
            }

            $user = new User;
            $user->first_name = $firstName;
            $user->last_name = $lastName;
            $user->email = $email;
            $user->password = Hash::make($data->password);
            $user->status = UserStatus::Active;
            $user->preferred_locale = 'en';

            if ($data->markEmailVerified) {
                $user->email_verified_at = now();
            }

            $user->save();
            $user->assignRole(Role::Admin->value);
            $this->permissionRegistrar->forgetCachedPermissions();

            $this->recordAuditEvent->execute(new AuditEventData(
                event: AuditEvent::AdministratorCreated,
                actorUserId: $actorUserId,
                subjectType: 'user',
                subjectId: (string) $user->id,
                requestId: $requestId,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                newValues: [
                    'roles' => [Role::Admin->value],
                    'status' => UserStatus::Active->value,
                    'email_verified' => $data->markEmailVerified,
                ],
                metadata: [
                    'email' => $user->email,
                ],
            ));

            return [
                'user' => $user->fresh(['roles']) ?? $user,
                'created' => true,
                'promoted' => false,
            ];
        });
    }
}
