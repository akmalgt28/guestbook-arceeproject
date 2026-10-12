<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('map_url')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('status')->default('draft')->index();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('event_user', function (Blueprint $table): void {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('assignment');
            $table->timestamps();
            $table->unique(['event_id', 'user_id', 'assignment']);
        });

        Schema::create('invitation_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('schema');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('invitation_templates')->restrictOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_templates');
        Schema::dropIfExists('invitation_templates');
        Schema::dropIfExists('event_user');
        Schema::dropIfExists('events');
    }
};
