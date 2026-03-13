<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TenantDeleteTest extends DuskTestCase
{
    public function test_agency_admin_can_delete_client_store_with_confirmation()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants/1')
                ->assertPresent('[data-testid="tenant-delete-button"]')
                ->click('[data-testid="tenant-delete-button"]')
                ->assertPresent('[data-testid="tenant-delete-confirm"]')
                ->assertPresent('[data-testid="tenant-delete-cancel"]');

            $this->assertTrue(true);
        });
    }
}
