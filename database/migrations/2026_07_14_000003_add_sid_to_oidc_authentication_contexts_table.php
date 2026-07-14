<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oidc_authentication_contexts', function (Blueprint $table): void {
            $table->ulid('sid')->nullable()->index()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('oidc_authentication_contexts', function (Blueprint $table): void {
            $table->dropColumn('sid');
        });
    }
};
