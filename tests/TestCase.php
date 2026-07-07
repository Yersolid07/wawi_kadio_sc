<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        
        // Seed roles and permissions for tests
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }
}
