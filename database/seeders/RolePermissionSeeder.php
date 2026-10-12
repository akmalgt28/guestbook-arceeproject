<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'events.view',
            'events.create',
            'events.update',
            'events.delete',
            'recipients.view',
            'recipients.create',
            'recipients.update',
            'recipients.delete',
            'checkins.view',
            'checkins.create',
            'guestbook.view',
            'guestbook.moderate',
            'media.view',
            'media.upload',
            'media.delete',
            'reports.view',
            'reports.export',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'audit_logs.view',
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::query()->firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'])->syncPermissions(Permission::query()->get());
        Role::query()->firstOrCreate(['name' => 'Event Admin', 'guard_name' => 'web'])->syncPermissions(Permission::query()->whereIn('name', [
            'events.view', 'events.create', 'events.update', 'events.delete',
            'recipients.view', 'recipients.create', 'recipients.update', 'recipients.delete',
            'checkins.view', 'checkins.create', 'guestbook.view', 'guestbook.moderate',
            'media.view', 'media.upload', 'media.delete', 'reports.view', 'reports.export',
        ])->get());
        Role::query()->firstOrCreate(['name' => 'QR Scanner', 'guard_name' => 'web'])->syncPermissions(Permission::query()->whereIn('name', [
            'events.view', 'checkins.view', 'checkins.create',
        ])->get());
    }
}
