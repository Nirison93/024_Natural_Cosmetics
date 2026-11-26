<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BatchExpiryReportInfoSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private readonly array $metadata)
    {
    }

    public function array(): array
    {
        $company = $this->metadata['company'];
        $rows = [
            [$this->metadata['title']],
            [$company?->company_name ?? ''],
            ['Generated At', $this->metadata['generated_at']],
            [],
            ['Selected Filters'],
        ];
        foreach ($this->metadata['filters'] as $label => $value) {
            $rows[] = [$label, is_array($value) ? json_encode($value) : $value];
        }
        $rows[] = [];
        $rows[] = ['Summary'];
        foreach ($this->metadata['summary'] as $label => $value) {
            $formattedLabel = str($label)->replace('_', ' ')->title()->toString();
            if (is_array($value)) {
                $value = json_encode($value);
            } elseif (is_numeric($value)) {
                $value = str_contains(strtolower((string) $label), 'value')
                    ? $this->metadata['currency'] . ' ' . number_format((float) $value, 2)
                    : number_format((float) $value, 2);
            }
            $rows[] = [$formattedLabel, $value];
        }
        $rows[] = [];
        $rows[] = ['Company Address', $company?->address ?? ''];
        $rows[] = ['Phone', $company?->phone ?? ''];
        $rows[] = ['Email', $company?->email ?? ''];
        $rows[] = ['Website', $company?->website ?? ''];
        $rows[] = ['Currency', $this->metadata['currency']];
        return $rows;
    }

    public function title(): string
    {
        return 'Report Info';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            5 => ['font' => ['bold' => true]],
        ];
    }
}
