<?php

namespace App\Enums;

enum TenantStatus: string
{
    case ACTIVE = 'active';
    case PENDING_SETUP = 'pending_setup';
    case SYNC_ERROR = 'sync_error';
    case SUSPENDED = 'suspended';
}
