<?php

namespace App\Enums;

enum SyncStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PARTIALLY_FAILED = 'partially_failed';
}
