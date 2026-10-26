<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    // Compiled views go to /tmp (VIEW_COMPILED_PATH in phpunit.xml), not storage/:
    // on the server that folder belongs to the web server's user.
}
