<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth\Pipeline;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccessTokenPipeline
{
    /** @var list<Closure(ClientCredentialsEvent, AccessTokenApi): void> */
    private array $clientCredentialsTriggers = [];

    /** @var list<Closure(TokenExchangeEvent, AccessTokenApi): void> */
    private array $tokenExchangeTriggers = [];

    public function registerClientCredentials(Closure $trigger): void
    {
        $this->clientCredentialsTriggers[] = $trigger;
    }

    public function registerTokenExchange(Closure $trigger): void
    {
        $this->tokenExchangeTriggers[] = $trigger;
    }

    public function runClientCredentials(ClientCredentialsEvent $event): AccessTokenApi
    {
        $api = new AccessTokenApi;

        foreach ($this->clientCredentialsTriggers as $trigger) {
            try {
                $trigger($event, $api);
            } catch (Throwable $exception) {
                Log::error('oidc: clientCredentials trigger threw; denying access token (fail-closed): '.$exception->getMessage(), [
                    'exception' => $exception,
                ]);
                $api->deny('client_credentials_trigger_error');

                return $api;
            }

            if ($api->isDenied()) {
                return $api;
            }
        }

        return $api;
    }

    public function runTokenExchange(TokenExchangeEvent $event): AccessTokenApi
    {
        $api = new AccessTokenApi;

        foreach ($this->tokenExchangeTriggers as $trigger) {
            try {
                $trigger($event, $api);
            } catch (Throwable $exception) {
                Log::error('oidc: tokenExchange trigger threw; denying access token (fail-closed): '.$exception->getMessage(), [
                    'exception' => $exception,
                ]);
                $api->deny('token_exchange_trigger_error');

                return $api;
            }

            if ($api->isDenied()) {
                return $api;
            }
        }

        return $api;
    }
}
