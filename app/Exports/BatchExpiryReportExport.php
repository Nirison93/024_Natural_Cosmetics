<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BatchExpiryReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Builder $query,
        private readonly array $columns,
        private readonly array $metadata
    ) {
    }

    public function sheets(): array
    {
        return [
            new BatchExpiryReportInfoSheet($this->metadata),
            new BatchExpiryReportRowsSheet($this->query, $this->columns, $this->metadata['currency']),
        ];
    }
}
