<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A consuming app is free to run on `Date::use(CarbonImmutable::class)`, whose
 * instances neither extend Illuminate\Support\Carbon nor mutate in place.
 * `composer test:immutable` replays the whole suite under it; a test that
 * switches the date class itself restores this one afterwards.
 */
function testDateClass(): string
{
    return getenv('OIDC_TEST_DATES') === 'immutable' ? CarbonImmutable::class : Carbon::class;
}

Date::use(testDateClass());

require_once dirname(__DIR__).'/packages/server/tests/Pest.php';
require_once dirname(__DIR__).'/packages/client/tests/Pest.php';
require_once dirname(__DIR__).'/packages/ui/tests/Pest.php';
