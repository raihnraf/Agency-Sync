<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TailwindStylingTest extends DuskTestCase
{
    public function test_dashboard_uses_tailwindcss_for_styling()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants')
                ->assertPresent('[class*="bg-"]')      // Tailwind background classes
                ->assertPresent('[class*="text-"]')    // Tailwind text classes
                ->assertPresent('[class*="p-"]')       // Tailwind padding classes
                ->assertPresent('[class*="flex"]')     // Tailwind flexbox classes
                ->assertScript('typeof window.tailwind !== "undefined"');

            $this->assertTrue(true);
        });
    }
}
