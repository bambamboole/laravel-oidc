<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Ui\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(TestCase::class)->in(__DIR__);
uses(RefreshDatabase::class)->in(__DIR__);

/**
 * The X-Inertia header makes Inertia answer with the page payload as JSON instead
 * of rendering a host application's root Blade view, which this package does not ship.
 */
function inertiaRequest(): Request
{
    $request = Request::create('/', 'GET');
    $request->headers->set('X-Inertia', 'true');

    return $request;
}

function renderPage(object $page): string
{
    return (string) $page->toResponse(inertiaRequest())->getContent();
}
