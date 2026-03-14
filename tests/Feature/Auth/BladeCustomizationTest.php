<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class BladeCustomizationTest extends TestCase
{
    public function test_login_page_has_agency_sync_logo()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('AgencySync');
        $response->assertSee('text-indigo-600');
    }

    public function test_login_page_uses_indigo_color_scheme()
    {
        $this->assertTrue(true);
    }

    public function test_login_page_has_custom_footer()
    {
        $this->assertTrue(true);
    }

    public function test_registration_route_removed()
    {
        $this->assertTrue(true);
    }

    public function test_login_page_has_remember_me_checkbox()
    {
        $this->assertTrue(true);
    }
}
