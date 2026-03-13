<?php

namespace App\Services;

use App\Models\JobStatus;
use Illuminate\Contracts\Queue\Job;

class QueueJobTracker
{
    public function track(Job $job, string $jobType): JobStatus
    {
        return JobStatus::create([
            'job_id' => $job->getJobId(),
            'tenant_id' => property_exists($job, 'tenantId') ? $job->tenantId : null,
            'job_type' => $jobType,
            'status' => 'pending',
            'payload' => $job->payload(),
        ]);
    }

    public function markAsRunning(Job $job): void
    {
        $status = JobStatus::where('job_id', $job->getJobId())->first();

        // Create status if it doesn't exist (for jobs dispatched directly, not via controller)
        if (!$status) {
            $payload = json_decode($job->getRawBody(), true);
            $command = $payload['data']['command'] ?? [];

            // Extract tenantId from serialized job command
            $tenantId = null;
            if (is_string($command)) {
                // Try to extract tenantId using multiple patterns
                if (preg_match('/s:8:"tenantId";s:36:"([a-f0-9-]{36})"/', $command, $matches)) {
                    $tenantId = $matches[1];
                } elseif (preg_match('/s:8:"tenantId";s:\d+:"([^"]+)"/', $command, $matches)) {
                    $tenantId = $matches[1];
                }
            }

            $status = JobStatus::create([
                'job_id' => $job->getJobId(),
                'tenant_id' => $tenantId,
                'job_type' => $payload['data']['commandName'] ?? 'Unknown',
                'status' => 'running',
                'payload' => $payload,
            ]);
        } else {
            $status->markAsRunning();
        }
    }

    public function markAsCompleted(Job $job): void
    {
        $status = JobStatus::where('job_id', $job->getJobId())->first();
        if ($status) {
            $status->markAsCompleted();
        }
    }

    public function markAsFailed(Job $job, \Throwable $exception): void
    {
        $status = JobStatus::where('job_id', $job->getJobId())->first();
        if ($status) {
            $status->markAsFailed($exception->getMessage());
        }
    }
}
