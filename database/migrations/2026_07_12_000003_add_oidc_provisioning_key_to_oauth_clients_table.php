<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }

    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->string('oidc_provisioning_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropUnique(['oidc_provisioning_key']);
            $table->dropColumn('oidc_provisioning_key');
        });
    }
};
