<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkins', function (Blueprint $table): void {
            $table->foreignId('invitation_id')->nullable()->change();
        });

        Schema::table('recipients', function (Blueprint $table): void {
            $table->string('organization')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table): void {
            $table->dropColumn('organization');
        });

        Schema::table('checkins', function (Blueprint $table): void {
            $table->foreignId('invitation_id')->nullable(false)->change();
        });
    }
};
