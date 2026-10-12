<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsvps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attending_count')->default(0);
            $table->timestamp('responded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rsvp_attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rsvp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('unknown');
            $table->timestamps();
            $table->index(['rsvp_id', 'status']);
        });

        Schema::create('qr_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->string('status')->default('active');
            $table->timestamp('generated_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
            $table->index(['invitation_id', 'status']);
        });

        Schema::create('checkins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_in_by')->constrained('users')->restrictOnDelete();
            $table->string('method');
            $table->string('status')->default('accepted');
            $table->unsignedSmallInteger('actual_attendee_count')->default(0);
            $table->timestamp('checked_in_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['event_id', 'recipient_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('rsvp_attendees');
        Schema::dropIfExists('rsvps');
    }
};
