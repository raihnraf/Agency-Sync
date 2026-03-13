<?php

namespace Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use App\Enums\TenantStatus;

class TenantStatusTest extends TestCase
{
    public function test_tenant_status_has_active_case(): void
    {
        $this->assertTrue(
            in_array(TenantStatus::tryFrom('active'), TenantStatus::cases(), true)
        );
    }

    public function test_tenant_status_has_pending_setup_case(): void
    {
        $this->assertTrue(
            in_array(TenantStatus::tryFrom('pending_setup'), TenantStatus::cases(), true)
        );
    }

    public function test_tenant_status_has_sync_error_case(): void
    {
        $this->assertTrue(
            in_array(TenantStatus::tryFrom('sync_error'), TenantStatus::cases(), true)
        );
    }

    public function test_tenant_status_has_suspended_case(): void
    {
        $this->assertTrue(
            in_array(TenantStatus::tryFrom('suspended'), TenantStatus::cases(), true)
        );
    }

    public function test_tenant_status_has_exactly_four_cases(): void
    {
        $this->assertCount(4, TenantStatus::cases());
    }
}
