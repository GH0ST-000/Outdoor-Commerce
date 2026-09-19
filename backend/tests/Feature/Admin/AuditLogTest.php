<?php

declare(strict_types=1);

use App\Domains\Identity\Enums\Role;
use App\Domains\Operations\Actions\RecordAuditEventAction;
use App\Domains\Operations\DTOs\AuditEventData;
use App\Domains\Operations\Enums\AuditEvent;
use App\Domains\Operations\Models\AuditLog;
use Tests\Support\InteractsWithAccessControl;

uses(InteractsWithAccessControl::class);

it('lists and shows audit logs for authorized admins only', function (): void {
    $admin = $this->createAdmin();
    $catalog = $this->createUserWithRole(Role::CatalogManager);

    app(RecordAuditEventAction::class)->execute(new AuditEventData(
        event: AuditEvent::UserStatusChanged,
        actorUserId: $admin->id,
        subjectType: 'user',
        subjectId: '1',
        requestId: '11111111-1111-1111-1111-111111111111',
        oldValues: ['status' => 'active', 'password' => 'secret'],
        newValues: ['status' => 'disabled', 'token' => 'abc'],
        metadata: ['cookie' => 'session', 'note' => 'safe'],
    ));

    $this->actingAs($catalog, 'web')
        ->getJson('/api/v1/admin/audit-logs')
        ->assertForbidden();

    $this->app['auth']->forgetGuards();

    $list = $this->actingAs($admin, 'web')
        ->getJson('/api/v1/admin/audit-logs?event=user_status_changed')
        ->assertOk();

    $id = $list->json('data.0.id');
    expect($id)->not->toBeNull();
    expect($list->json('data.0.old_values'))->not->toHaveKey('password');
    expect($list->json('data.0.new_values'))->not->toHaveKey('token');
    expect($list->json('data.0.metadata'))->not->toHaveKey('cookie');
    expect($list->json('data.0.metadata.note'))->toBe('safe');

    $this->actingAs($admin, 'web')
        ->getJson("/api/v1/admin/audit-logs/{$id}")
        ->assertOk()
        ->assertJsonPath('data.request_id', '11111111-1111-1111-1111-111111111111');
});

it('is append-only at the model layer', function (): void {
    $log = app(RecordAuditEventAction::class)->execute(new AuditEventData(
        event: AuditEvent::AdministratorCreated,
        subjectType: 'user',
        subjectId: '9',
    ));

    expect(fn () => $log->update(['event' => AuditEvent::AdminAccessDenied->value]))
        ->toThrow(RuntimeException::class);

    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
    expect(AuditLog::query()->count())->toBe(1);
});
