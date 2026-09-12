<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `id` is what descendant tokens are labelled with; `code` is the secret the
 * browser carries back from the redirect. Keeping them apart keeps a 320-bit
 * credential out of every row that merely points at this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oidc_auth_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('code', 80)->unique();
            $table->string('realm_id')->index();
            $table->foreignUuid('user_id')->index();
            $table->foreignUuid('client_id')->index();
            $table->json('scopes')->nullable();
            $table->json('audience')->nullable();
            $table->string('redirect_uri', 2048)->nullable();
            $table->string('code_challenge', 128);
            $table->string('code_challenge_method', 8);
            $table->string('nonce')->nullable();
            $table->unsignedBigInteger('auth_time')->nullable();
            $table->uuid('context_id')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oidc_auth_codes');
    }
};
