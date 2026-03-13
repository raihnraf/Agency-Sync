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
        if ($status) {
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
