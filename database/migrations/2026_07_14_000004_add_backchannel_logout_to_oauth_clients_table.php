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
            $table->text('backchannel_logout_uri')->nullable()->after('post_logout_redirect_uris');
            $table->boolean('backchannel_logout_session_required')->default(false)->after('backchannel_logout_uri');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn(['backchannel_logout_uri', 'backchannel_logout_session_required']);
        });
    }
};
