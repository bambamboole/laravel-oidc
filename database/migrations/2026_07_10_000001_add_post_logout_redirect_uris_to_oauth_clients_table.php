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
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->text('post_logout_redirect_uris')->nullable()->after('redirect_uris');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('post_logout_redirect_uris');
        });
    }
};
