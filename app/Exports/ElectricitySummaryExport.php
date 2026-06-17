<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ElectricitySummaryExport implements FromCollection, WithHeadings
{
    protected array $summary;

    public function __construct(array $summary)
    {
        $this->summary = $summary;
    }

    public function collection(): Collection
    {
        return collect($this->summary)->map(function ($item) {
            return [
                $item['nama_cabang'] ?? '-',
                $item['kode_cabang'] ?? '-',
                $item['nomor_meter'] ?? '-',
                $item['periode'] ?? '-',
                $item['tanggal_awal'] ?? '-',
                $item['tanggal_akhir'] ?? '-',
                $item['kwh_awal'] ?? 0,
                $item['kwh_akhir'] ?? 0,
                $item['pemakaian_kwh'] ?? 0,
                $item['estimasi_harga_per_kwh'] ?? 0,
                $item['estimasi_pemakaian_rupiah'] ?? 0,
                $item['estimasi_sisa_rupiah'] ?? 0,
                $item['rekomendasi_topup_bulan_depan'] ?? 0,
                $item['jumlah_foto'] ?? 0,
                $item['notes_text'] ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Cabang',
            'Kode Cabang',
            'Nomor Meter',
            'Periode',
            'Tanggal Awal',
            'Tanggal Akhir',
            'kWh Awal',
            'kWh Akhir',
            'Pemakaian kWh',
            'Harga per kWh',
            'Estimasi Pemakaian Rupiah',
            'Estimasi Sisa Rupiah',
            'Rekomendasi Topup Bulan Depan',
            'Jumlah Foto',
            'Catatan',
        ];
    }
}   