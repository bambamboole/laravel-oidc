<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Ui\Layouts\AuthLayout;
use Illuminate\Http\Request;
use Lattice\Lattice\Core\PageSchema;

it('renders the configured brand icon', function () {
    config()->set('oidc-ui.brand_icon', 'acme-logo');

    $renderable = (new AuthLayout)->schema(PageSchema::make(), Request::create('/'))->renderable();

    expect(json_encode($renderable))->toContain('acme-logo');
});
