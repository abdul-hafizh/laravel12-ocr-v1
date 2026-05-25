<?php

namespace App\Services;

class ImageAnalysisPromptService
{
    public static function getPrompt(string $scanType): string
    {
        return match ($scanType) {
            'electricity' => self::electricityPrompt(),
            'online_receipt' => self::onlineReceiptPrompt(),
            'printer' => self::printerPrompt(),
        };
    }

    private static function electricityPrompt(): string
    {
        return '
            Analisis gambar meteran listrik / token listrik ini.

            Kembalikan hanya JSON valid:
            {
                "valid": true,
                "message": "",
                "data_penting": {
                    "tanggal": null,
                    "kwh": null,
                    "barcode": null,
                    "nomor_meter": null,
                    "nomor_token": null,
                    "lokasi": null,
                    "alamat_lengkap": null,
                    "kecamatan": null,
                    "kota": null,
                    "provinsi": null
                }
            }

            Jika bukan gambar listrik/token/kWh:
            {
                "valid": false,
                "message": "Gambar tidak sesuai. Data listrik/token/kWh tidak ditemukan.",
                "data_penting": {}
            }
            ';
    }

    private static function onlineReceiptPrompt(): string
    {
        return '
            Analisis gambar struk online / invoice / bukti transaksi online.

            Syarat valid:
            - Harus terlihat nominal pembayaran / total pembayaran / jumlah uang.

            PENTING:
            - Nominal HARUS integer.
            - Hapus Rp, IDR, titik, koma, dan spasi.
            - Contoh "Rp. 12.000" menjadi 12000.

            Kembalikan hanya JSON valid:
            {
                "valid": true,
                "message": "",
                "data_penting": {
                    "tanggal": null,
                    "nama_toko": null,
                    "nama_pembeli": null,
                    "nomor_pesanan": null,
                    "nomor_referensi": null,
                    "total_pembayaran": 12000,
                    "metode_pembayaran": null,
                    "status_pembayaran": null
                }
            }

            Jika tidak valid:
            {
                "valid": false,
                "message": "Gambar tidak sesuai. Nominal pembayaran tidak ditemukan.",
                "data_penting": {}
            }
            ';
    }

    private static function printerPrompt(): string
    {
        return '
            Analisis gambar mesin cetak / printer / fotocopy / check counter.

            Syarat valid:
            - Harus terlihat serial number mesin.
            - Harus terlihat counter Black & White.
            - Harus terlihat counter Full Color.
            - Harus terlihat counter Long Sheet.
            - Jika serial number tidak ada, kembalikan valid false.

            Mapping:
            - total_black_white = total_black_white_large + total_black_white_small
            - total_color = total_full_color_large + total_full_color_small
            - total_long_sheet ambil dari Total (Long Sheet)
            - Semua angka harus integer.
            - Hilangkan nol di depan.

            Kembalikan hanya JSON valid:
            {
                "valid": true,
                "message": "",
                "data_penting": {
                    "tanggal": "1 Maret 2026",
                    "lokasi": "Tebet",
                    "nama_mesin": "iPR C710",
                    "serial_number": "2NT02555",
                    "total_black_white_large": 27624,
                    "total_black_white_small": 33680,
                    "total_full_color_large": 1047569,
                    "total_full_color_small": 616046,
                    "total_long_sheet": 94,
                    "total_black_white": 61304,
                    "total_color": 1663615,
                    "total": 1724919
                }
            }

            Jangan kembalikan ringkasan, teks_terbaca, jenis_gambar, atau confidence.

            Jika tidak valid:
            {
                "valid": false,
                "message": "Serial number atau data counter mesin tidak ditemukan.",
                "data_penting": {}
            }
            ';
    }
}