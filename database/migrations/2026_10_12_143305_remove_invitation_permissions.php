<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'templates.view', 'templates.create', 'templates.update', 'templates.delete',
                'invitations.view', 'invitations.create', 'invitations.update', 'invitations.delete', 'invitations.send',
                'rsvps.view', 'rsvps.update', 'qr_codes.view', 'qr_codes.generate',
            ])
            ->pluck('id');

        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    public function down(): void
    {
        throw new RuntimeException('Invitation permissions cleanup is irreversible.');
    }
};
