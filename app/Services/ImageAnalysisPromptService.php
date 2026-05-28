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
            Analisis gambar struk online / invoice / bukti transaksi online / bukti transfer / bukti pembayaran / pembelian token listrik.

            Syarat valid:
            - Harus terlihat nominal pembayaran / total bayar / jumlah uang / total transaksi.
            - Jika struk adalah pembelian token listrik PLN, tetap anggap valid sebagai struk online.

            PENTING:
            - Semua nominal uang HARUS integer.
            - Hapus Rp, IDR, titik, koma, spasi, dan desimal.
            - Contoh "Rp 1.003.500" menjadi 1003500.
            - Contoh "Rp 97.001,00" menjadi 97001.
            - Jika data tidak terlihat, isi null.
            - Jangan mengarang data.
            - Kembalikan hanya JSON valid tanpa markdown.

            Kembalikan format JSON berikut:
            {
                "valid": true,
                "message": "",
                "data_penting": {
                    "jenis_struk": null,
                    "bank_atau_aplikasi": null,
                    "status_transaksi": null,

                    "tanggal_transaksi": null,
                    "waktu_transaksi": null,

                    "total_pembayaran": null,
                    "rekening_sumber": null,
                    "nama_rekening_sumber": null,

                    "terminal": null,
                    "jenis_pembelian": null,
                    "nomor_transaksi": null,
                    "nomor_struk": null,
                    "nomor_referensi": null,

                    "nama_toko": null,
                    "nama_pembeli": null,
                    "nomor_pesanan": null,
                    "metode_pembayaran": null,

                    "produk": null,
                    "provider": null,

                    "nomor_meter": null,
                    "id_pelanggan": null,
                    "nama_pelanggan": null,
                    "tarif_daya": null,
                    "no_ref": null,

                    "rp_bayar": null,
                    "materai": null,
                    "ppn": null,
                    "ppj_tl": null,
                    "angsuran": null,
                    "rp_stroom_token": null,
                    "jumlah_kwh": null,
                    "stroom_token": null,
                    "admin_bank": null,

                    "catatan": null
                }
            }

            Penjelasan field khusus token listrik:
            - nomor_meter ambil dari NO METER.
            - id_pelanggan ambil dari IDPEL.
            - nama_pelanggan ambil dari NAMA.
            - tarif_daya ambil dari TARIF/DAYA.
            - no_ref ambil dari NO REF.
            - rp_bayar ambil dari RP BAYAR.
            - rp_stroom_token ambil dari RP STROOM/TOKEN.
            - jumlah_kwh ambil dari JML KWH.
            - stroom_token ambil dari STROOM/TOKEN.
            - admin_bank ambil dari ADMIN BANK.

            Jika tidak valid:
            {
                "valid": false,
                "message": "Gambar tidak sesuai. Nominal pembayaran atau data transaksi tidak ditemukan.",
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
                    "total_black_white_large": 0,
                    "total_black_white_small": 0,
                    "total_full_color_large": 0,
                    "total_full_color_small": 0,
                    "total_long_sheet": 0,
                    "total_black_white": 0,
                    "total_color": 0,
                    "total": 0
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