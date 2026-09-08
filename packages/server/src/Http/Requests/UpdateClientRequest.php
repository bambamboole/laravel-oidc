<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Http\Requests;

final class UpdateClientRequest extends ClientRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'client_name' => ['sometimes', 'string', 'max:255'],
            'grant_types' => ['sometimes', 'array', 'min:1'],
            'grant_types.*' => ['string'],
            'redirect_uris' => ['sometimes', 'array'],
            'redirect_uris.*' => ['string'],
            'post_logout_redirect_uris' => ['sometimes', 'array'],
            'post_logout_redirect_uris.*' => ['string'],
            'backchannel_logout_uri' => ['sometimes', 'nullable', 'string'],
            'backchannel_logout_session_required' => ['sometimes', 'boolean'],
            'scopes' => ['sometimes', 'nullable', 'array'],
            'scopes.*' => ['string'],
            'allowed_exchange_audiences' => ['sometimes', 'array'],
            'allowed_exchange_audiences.*' => ['string'],
            'trusted' => ['sometimes', 'boolean'],
        ];
    }
}
