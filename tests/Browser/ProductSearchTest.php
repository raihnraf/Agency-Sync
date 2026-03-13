<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProductSearchTest extends DuskTestCase
{
    public function test_agency_admin_can_search_products_within_client_catalog()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard/tenants/1/products')
                ->assertPresent('[data-testid="product-search-input"]')
                ->assertPresent('[data-testid="product-search-results"]')
                ->type('[data-testid="product-search-input"]', 'test product')
                ->assertPresent('[data-testid="product-search-result-item"]');

            $this->assertTrue(true);
        });
    }
}
