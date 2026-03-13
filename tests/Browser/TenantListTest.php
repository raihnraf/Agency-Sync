<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TenantListTest extends DuskTestCase
{
    public function test_agency_admin_can_view_client_store_list_with_status_indicators()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants')
                ->assertSee('Client Stores')
                ->assertPresent('[data-testid="tenant-list"]')
                ->assertPresent('[data-testid="tenant-status"]');

            $this->assertTrue(true);
        });
    }
}
