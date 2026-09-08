<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared body handling for the client administration API: the body must be
 * a JSON object, read-only fields are ignored so a fetched representation
 * can be sent back, and unknown fields are rejected so a misspelt key never
 * passes silently.
 */
abstract class ClientRequest extends FormRequest
{
    public const array Fields = [
        'client_name',
        'grant_types',
        'redirect_uris',
        'post_logout_redirect_uris',
        'backchannel_logout_uri',
        'backchannel_logout_session_required',
        'scopes',
        'allowed_exchange_audiences',
        'trusted',
    ];

    private const array ReadOnlyFields = ['client_id', 'confidential', 'created_at', 'updated_at'];

    private const array DefinitionProperties = [
        'client_name' => 'name',
        'grant_types' => 'grantTypes',
        'redirect_uris' => 'redirectUris',
        'post_logout_redirect_uris' => 'postLogoutRedirectUris',
        'backchannel_logout_uri' => 'backchannelLogoutUri',
        'backchannel_logout_session_required' => 'backchannelLogoutSessionRequired',
        'scopes' => 'scopes',
        'allowed_exchange_audiences' => 'allowedExchangeAudiences',
        'trusted' => 'trusted',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $content = (string) $this->getContent();
        $body = json_validate($content) ? json_decode($content, true) : null;

        if (! is_array($body) || array_is_list($body) && $body !== []) {
            abort(400, 'The request body must be a JSON object.');
        }

        $this->json()->replace(array_diff_key($body, array_flip(self::ReadOnlyFields)));
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_diff(array_keys($this->json()->all()), self::Fields) as $field) {
                    $validator->errors()->add((string) $field, 'This field is not supported.');
                }
            },
        ];
    }

    /**
     * The validated fields keyed by their ClientDefinition constructor
     * argument, only for the fields the body actually carried.
     *
     * @return array<string, mixed>
     */
    public function definitionChanges(): array
    {
        $validated = $this->validated();
        $changes = [];

        foreach (self::DefinitionProperties as $field => $property) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $changes[$property] = match ($property) {
                'trusted', 'backchannelLogoutSessionRequired' => (bool) $validated[$field],
                'scopes' => is_array($validated[$field]) ? array_values($validated[$field]) : null,
                'grantTypes', 'redirectUris', 'postLogoutRedirectUris', 'allowedExchangeAudiences' => array_values((array) $validated[$field]),
                default => $validated[$field],
            };
        }

        return $changes;
    }
}
