<?php

declare(strict_types=1);

use Bambamboole\LaravelOidc\Testing\InteractsWithOidc;
use Bambamboole\LaravelOidc\Tests\TestCase;

/*
 * Pest supports nested per-directory Pest.php files, so the uses() below
 * likely also applies at runtime here — harmlessly, since it duplicates the
 * root tests/Pest.php bindings plus the trait the test files in this
 * directory already compose themselves via a file-local uses() call. Its
 * load-bearing purpose is peststan (which only reads uses() declarations
 * from files named Pest.php): it resolves `$this` as TestCase&InteractsWithOidc
 * inside this directory's test closures. Do not delete it as dead code —
 * phpstan would regress, and the argument.templateType ignore for
 * tests/Testing in phpstan.neon.dist is tied to the intersection type this
 * shim produces.
 */
uses(TestCase::class, InteractsWithOidc::class);
