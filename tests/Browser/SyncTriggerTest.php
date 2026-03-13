<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SyncTriggerTest extends DuskTestCase
{
    public function test_agency_admin_can_trigger_sync_operation()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants/1')
                ->assertPresent('[data-testid="sync-trigger-button"]')
                ->click('[data-testid="sync-trigger-button"]')
                ->assertSee('Sync started');

            $this->assertTrue(true);
        });
    }
}
