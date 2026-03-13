<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AlpineComponentsTest extends DuskTestCase
{
    public function test_dashboard_uses_alpine_js_for_interactivity()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants')
                ->assertPresent('[x-data]')  // Alpine.js component root
                ->assertPresent('[x-cloak]') // Alpine.js cloak directive
                ->waitFor('[x-init]')        // Alpine.js initialized
                ->assertScript('typeof window.Alpine !== "undefined"');

            $this->assertTrue(true);
        });
    }
}
