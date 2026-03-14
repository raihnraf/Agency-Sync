<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

class ExportService
{
    /**
     * Generate export filename with pattern: {type}_{tenant_slug}_{date}.{ext}
     *
     * @param string $type Export type (e.g., 'synclogs', 'products')
     * @param Tenant $tenant Tenant model for slug extraction
     * @param string $format File format ('csv' or 'xlsx')
     * @return string Generated filename
     */
    public function generateFilename(string $type, Tenant $tenant, string $format): string
    {
        $date = now()->format('Ymd');
        $slug = $tenant->slug;
        $ext = $format === 'csv' ? 'csv' : 'xlsx';
        return "{$type}_{$slug}_{$date}.{$ext}";
    }

    /**
     * Apply filters to query builder for export data
     *
     * @param Builder $query Query builder to filter
     * @param array $filters Filter parameters (start_date, end_date, tenant_id, status)
     * @return Builder Filtered query builder
     */
    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Estimate row count for export query
     *
     * @param Builder $query Query builder to count
     * @return int Estimated row count
     */
    public function estimateRowCount(Builder $query): int
    {
        return $query->count();
    }
}
