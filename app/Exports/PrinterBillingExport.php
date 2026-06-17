<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PrinterBillingExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $billings)
    {
    }

    public function collection()
    {
        return $this->billings->map(function ($item) {
            return [
                'tanggal' => $item->created_at,
                'mesin' => $item->master_nama_mesin ?: $item->nama_mesin,
                'serial_number' => $item->serial_number,
                'vendor' => $item->nama_vendor,
                'cabang' => ($item->kode_cabang ?? '-') . ' - ' . ($item->nama_cabang ?? '-'),

                'bw_a3' => $item->bw_a3,
                'bw_a4' => $item->bw_a4,
                'color_a3' => $item->color_a3,
                'color_a4' => $item->color_a4,
                'bw_long_sheet' => $item->bw_long_sheet,
                'color_long_sheet' => $item->color_long_sheet,

                'status' => $item->master_mesin_id ? 'OK' : 'BELUM MAPPING',
                'catatan' => $item->notes_text ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Mesin',
            'Serial Number',
            'Vendor',
            'Cabang',
            'BW A3',
            'BW A4',
            'Color A3',
            'Color A4',
            'BW Long Sheet',
            'Color Long Sheet',
            'Status',
            'Catatan',
        ];
    }
}