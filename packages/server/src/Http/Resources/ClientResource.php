<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Server\Http\Resources;

use Bambamboole\LaravelOidc\Server\Clients\ClientDefinition;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Passport\Client;

/**
 * @property Client $resource
 */
final class ClientResource extends JsonResource
{
    public static $wrap = null;

    private ?string $plainSecret = null;

    public function withSecret(?string $plainSecret): self
    {
        $this->plainSecret = $plainSecret;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $definition = ClientDefinition::fromClient($this->resource);

        return [
            'client_id' => (string) $this->resource->getKey(),
            /** The plaintext secret, present only in the create and rotate responses. */
            'client_secret' => $this->when($this->plainSecret !== null, fn (): string => (string) $this->plainSecret),
            'client_name' => $definition->name,
            /** Whether the client authenticates with a secret. Public clients only arrive through dynamic registration. */
            'confidential' => $this->resource->confidential(),
            /** @var list<string> */
            'grant_types' => $definition->grantTypes,
            /** @var list<string> */
            'redirect_uris' => $definition->redirectUris,
            /** @var list<string> */
            'post_logout_redirect_uris' => $definition->postLogoutRedirectUris,
            /** @var string|null */
            'backchannel_logout_uri' => $definition->backchannelLogoutUri,
            'backchannel_logout_session_required' => $definition->backchannelLogoutSessionRequired,
            /**
             * Null leaves the client unrestricted; an empty list allows no scope at all.
             *
             * @var list<string>|null
             */
            'scopes' => $definition->scopes,
            /** @var list<string> */
            'allowed_exchange_audiences' => $definition->allowedExchangeAudiences,
            'trusted' => $definition->trusted,
            'created_at' => $this->timestamp('created_at'),
            'updated_at' => $this->timestamp('updated_at'),
        ];
    }

    private function timestamp(string $attribute): ?string
    {
        $value = $this->resource->getAttribute($attribute);

        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : null;
    }
}
