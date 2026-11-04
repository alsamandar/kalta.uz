<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    // Compiled views go to /tmp (VIEW_COMPILED_PATH in phpunit.xml), not storage/:
    // on the server that folder belongs to the web server's user.

    /** What a visitor reads in Uzbek, the site's default language, for a string in the code. */
    protected function uz(string $key, array $replace = []): string
    {
        return __($key, $replace, 'uz');
    }
}
