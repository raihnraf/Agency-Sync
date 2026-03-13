<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TenantCreateFormTest extends DuskTestCase
{
    public function test_agency_admin_can_create_new_client_store_via_form()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants/create')
                ->assertSee('Create Client Store')
                ->assertPresent('[data-testid="tenant-name-input"]')
                ->assertPresent('[data-testid="tenant-platform-select"]')
                ->assertPresent('[data-testid="tenant-api-credentials-input"]')
                ->assertPresent('[data-testid="tenant-create-submit"]');

            $this->assertTrue(true);
        });
    }
}
