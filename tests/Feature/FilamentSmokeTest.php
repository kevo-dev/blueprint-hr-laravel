<?php

namespace Tests\Feature;

use Tests\TestCase;

class FilamentSmokeTest extends TestCase
{
    public function test_filament_admin_login_is_available(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}
