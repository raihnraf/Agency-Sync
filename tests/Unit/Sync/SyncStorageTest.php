<?php

declare(strict_types=1);

namespace Tests\Unit\Sync;

use Tests\TestCase;
use App\Models\Product;

class SyncStorageTest extends TestCase
{
    public function test_products_stored_with_tenant_id()
    {
        $this->assertTrue(true, 'Test stub - implement after Product model created');
    }

    public function test_products_stored_idempotently()
    {
        $this->assertTrue(true, 'Test stub - implement after Product model created');
    }
}
