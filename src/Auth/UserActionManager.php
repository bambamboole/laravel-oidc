<?php

declare(strict_types=1);

namespace Bambamboole\LaravelOidc\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use RuntimeException;

class UserActionManager
{
    /**
     * @var callable|class-string|null
     */
    private mixed $createUsersUsing = null;

    /**
     * @var callable|class-string|null
     */
    private mixed $resetUserPasswordsUsing = null;

    /**
     * @param  callable(array<string, mixed>): Authenticatable|class-string  $action
     */
    public function createUsersUsing(callable|string $action): void
    {
        $this->createUsersUsing = $action;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createUser(array $input): Authenticatable
    {
        $action = $this->resolveAction($this->createUsersUsing, 'create user', 'create');

        $user = is_callable($action)
            ? $action($input)
            : $action->create($input);

        if (! $user instanceof Authenticatable) {
            throw new RuntimeException('The OIDC create user action must return an authenticatable user.');
        }

        return $user;
    }

    /**
     * @param  callable(CanResetPassword, array<string, mixed>): void|class-string  $action
     */
    public function resetUserPasswordsUsing(callable|string $action): void
    {
        $this->resetUserPasswordsUsing = $action;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function resetUserPassword(CanResetPassword $user, array $input): void
    {
        $action = $this->resolveAction($this->resetUserPasswordsUsing, 'reset user password', 'reset');

        if (is_callable($action)) {
            $action($user, $input);

            return;
        }

        $action->reset($user, $input);
    }

    private function resolveAction(mixed $action, string $name, string $method): mixed
    {
        if ($action === null) {
            throw new RuntimeException("No OIDC {$name} action has been configured.");
        }

        if (is_string($action) && class_exists($action)) {
            $action = app($action);
        }

        if (is_callable($action)) {
            return $action;
        }

        if (is_object($action) && method_exists($action, $method)) {
            return $action;
        }

        throw new RuntimeException("The configured OIDC {$name} action is not callable.");
    }
}
