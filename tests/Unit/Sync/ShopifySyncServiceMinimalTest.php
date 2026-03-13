<?php

namespace Tests\Unit\Sync;

use Tests\TestCase;
use App\Services\Sync\ShopifySyncService;
use App\Services\Sync\ProductValidator;

class ShopifySyncServiceMinimalTest extends TestCase
{
    public function test_service_can_be_instantiated(): void
    {
        $validator = new ProductValidator();
        $service = new ShopifySyncService($validator, true);

        $this->assertInstanceOf(ShopifySyncService::class, $service);
    }
}
