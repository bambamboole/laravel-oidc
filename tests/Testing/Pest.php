<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Testing\InteractsWithOidc;
use Bambamboole\LaravelOidc\Tests\TestCase;

/*
 * This file is inert at runtime: Pest only boots the root tests/Pest.php, whose
 * uses()->in() bindings already cover this directory, and the test files here
 * compose InteractsWithOidc themselves via a file-local uses() call. It exists
 * solely so peststan (which only reads uses() declarations from files named
 * Pest.php) resolves `$this` as TestCase&InteractsWithOidc inside this
 * directory's test closures. Do not add real bindings here (they have no
 * runtime effect) and do not delete it as dead code — phpstan would regress,
 * and the argument.templateType ignore for tests/Testing in phpstan.neon.dist
 * is tied to the intersection type this shim produces.
 */
uses(TestCase::class, InteractsWithOidc::class);
