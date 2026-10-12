<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guestbook_entries', function (Blueprint $table): void {
            $table->dropForeign(['invitation_id']);
            $table->dropColumn('invitation_id');
        });

        Schema::table('checkins', function (Blueprint $table): void {
            $table->dropForeign(['invitation_id']);
            $table->dropColumn('invitation_id');
        });

        Schema::table('qr_codes', function (Blueprint $table): void {
            $table->dropForeign(['invitation_id']);
            $table->dropIndex('qr_codes_invitation_id_status_index');
            $table->dropUnique(['invitation_id']);
            $table->dropColumn('invitation_id');
            $table->foreignId('recipient_id')->unique()->constrained()->cascadeOnDelete();
        });

        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('rsvp_attendees');
        Schema::dropIfExists('rsvps');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('event_templates');
        Schema::dropIfExists('invitation_templates');
    }

    public function down(): void
    {
        throw new RuntimeException('Invitation system cleanup is irreversible.');
    }
};
