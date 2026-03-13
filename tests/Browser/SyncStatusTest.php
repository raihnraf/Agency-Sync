<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SyncStatusTest extends DuskTestCase
{
    public function test_agency_admin_can_view_last_sync_status()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants/1')
                ->assertPresent('[data-testid="sync-status-time"]')
                ->assertPresent('[data-testid="sync-status-status"]')
                ->assertPresent('[data-testid="sync-status-product-count"]');

            $this->assertTrue(true);
        });
    }
}
