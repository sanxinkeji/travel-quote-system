<?php

namespace Tests\Unit;

use Tests\TestCase;

class TestEnvironmentIsolationTest extends TestCase
{
    public function test_phpunit_uses_an_isolated_in_memory_database_even_when_runtime_config_is_cached(): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
