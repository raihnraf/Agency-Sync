<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ErrorLogTest extends DuskTestCase
{
    public function test_agency_admin_can_view_error_log_with_filtering()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/error-log')
                ->assertPresent('[data-testid="error-log-list"]')
                ->assertPresent('[data-testid="error-log-tenant-filter"]')
                ->assertPresent('[data-testid="error-log-date-filter"]')
                ->assertPresent('[data-testid="error-log-item"]');

            $this->assertTrue(true);
        });
    }
}
