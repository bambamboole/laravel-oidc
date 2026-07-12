<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth;

use Bambamboole\LaravelOidc\Auth\Models\AuthenticationContext;

class AuthenticationContextStore
{
    /**
     * @param  array{user_id: string, amr: list<string>, acr: ?string, auth_time: ?int, id_token_claims: array<string, mixed>}  $attributes
     */
    public function create(array $attributes): string
    {
        $context = new AuthenticationContext;
        $context->user_id = $attributes['user_id'];
        $context->amr = $attributes['amr'];
        $context->acr = $attributes['acr'];
        $context->auth_time = $attributes['auth_time'];
        $context->id_token_claims = $attributes['id_token_claims'];
        $context->access_token_claims = [];
        $context->created_at = now();
        $context->save();

        return $context->id;
    }

    public function find(string $id): ?AuthenticationContext
    {
        return AuthenticationContext::query()->find($id);
    }
}
