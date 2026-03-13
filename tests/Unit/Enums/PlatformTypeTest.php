<?php

namespace Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use App\Enums\PlatformType;

class PlatformTypeTest extends TestCase
{
    public function test_platform_type_has_shopify_case(): void
    {
        $this->assertTrue(
            in_array(PlatformType::tryFrom('shopify'), PlatformType::cases(), true)
        );
    }

    public function test_platform_type_has_shopware_case(): void
    {
        $this->assertTrue(
            in_array(PlatformType::tryFrom('shopware'), PlatformType::cases(), true)
        );
    }

    public function test_platform_type_has_exactly_two_cases(): void
    {
        $this->assertCount(2, PlatformType::cases());
    }
}
