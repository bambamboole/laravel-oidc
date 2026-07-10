<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Hooks;

use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClaimHooks
{
    /** @var array<string, list<Closure>> */
    private array $hooks = [];

    public function register(Trigger $trigger, Closure $hook): void
    {
        $this->hooks[$trigger->name][] = $hook;
    }

    public function run(Trigger $trigger, object $context): void
    {
        foreach ($this->hooks[$trigger->name] ?? [] as $hook) {
            try {
                $hook($context);
            } catch (Throwable $exception) {
                Log::error('oidc: claim hook threw and was skipped: '.$exception->getMessage(), [
                    'trigger' => $trigger->name,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
