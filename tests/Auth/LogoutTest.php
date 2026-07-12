<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Workbench\App\Models\User;

it('logs an authenticated user out and redirects to root', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('returns Fortify-compatible JSON after logout', function () {
    $user = User::create(['name' => 'M', 'email' => 'm@example.com', 'password' => Hash::make('password')]);

    $this->actingAs($user)
        ->postJson(route('logout'))
        ->assertNoContent();

    $this->assertGuest();
});
