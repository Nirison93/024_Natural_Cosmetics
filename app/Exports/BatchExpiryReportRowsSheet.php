<?php

namespace App\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BatchExpiryReportRowsSheet implements FromQuery, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithMapping, WithStyles, WithTitle
{
    public function __construct(
        private readonly Builder $query,
        private readonly array $columns,
        private readonly string $currency
    ) {
    }

    public function query(): Builder
    {
        return clone $this->query;
    }

    public function headings(): array
    {
        return array_column($this->columns, 'label');
    }

    public function map($row): array
    {
        return array_map(fn ($column) => data_get($row, $column['key']) ?? '', $this->columns);
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach ($this->columns as $index => $column) {
            if ($column['type'] === 'currency') {
                $symbol = str_replace('"', '""', $this->currency);
                $formats[Coordinate::stringFromColumnIndex($index + 1)] = '"' . $symbol . '" #,##0.00';
            } elseif ($column['type'] === 'number') {
                $formats[Coordinate::stringFromColumnIndex($index + 1)] = NumberFormat::FORMAT_NUMBER_00;
            }
        }
        return $formats;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1D4ED8']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Filtered Report Data';
    }
}
