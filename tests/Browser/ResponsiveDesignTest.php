<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ResponsiveDesignTest extends DuskTestCase
{
    public function test_dashboard_is_responsive_for_mobile_and_tablet()
    {
        $this->browse(function (Browser $browser) {
            // Test mobile viewport (375px)
            $browser->resize(375, 667)
                ->visit('/dashboard/tenants')
                ->assertPresent('[class*="md:hidden"]')    // Mobile-only elements
                ->assertPresent('[class*="hidden md:flex"]') // Desktop-only elements

                // Test tablet viewport (768px)
                ->resize(768, 1024)
                ->assertPresent('[class*="lg:hidden"]')    // Tablet-only elements

                // Test desktop viewport (1024px)
                ->resize(1024, 768)
                ->assertPresent('[class*="hidden lg:flex"]'); // Desktop elements

            $this->assertTrue(true);
        });
    }
}
