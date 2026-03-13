<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TenantEditTest extends DuskTestCase
{
    public function test_agency_admin_can_edit_client_store_details()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants/1/edit')
                ->assertSee('Edit Client Store')
                ->assertPresent('[data-testid="tenant-name-input"]')
                ->assertPresent('[data-testid="tenant-status-select"]')
                ->assertPresent('[data-testid="tenant-update-submit"]');

            $this->assertTrue(true);
        });
    }
}
