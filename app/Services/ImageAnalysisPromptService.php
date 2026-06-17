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
            'part_maintenance' => self::partMaintenancePrompt(),
            'cea'  => self::ceaPrompt(),
            'asaba' => self::asabaPrompt(),
        };
    }

    private static function electricityPrompt(): string
    {
        return '
            Anda adalah AI OCR khusus meteran listrik PLN Indonesia.

            Tugas:
            - Analisa gambar meter listrik/token listrik.
            - Baca semua angka dengan teliti.
            - Fokus utama pada:
            1. nilai kWh di layar LCD
            2. barcode / nomor meter yang berada DI BAWAH layar kWh
            3. nomor token jika ada
            4. tanggal pada foto
            5. lokasi/alamat overlay pada foto jika ada

            ATURAN PENTING:
            - Barcode/nomor meter biasanya berupa angka panjang di bawah layar kWh.
            - Ambil angka yang berada tepat di bawah barcode.
            - Jangan mengambil angka tulisan tangan putih besar pada cover meter jika ada barcode resmi.
            - Hilangkan spasi saat menyimpan barcode dan nomor meter.
            - Jika barcode terlihat seperti:
            "32 9027 2726 5"
            maka simpan menjadi:
            "32902727265"

            - Nilai kWh harus angka dari layar LCD meter.
            - Jika ada titik/koma pada kWh tetap pertahankan.
            - Jika ada beberapa angka, prioritaskan angka yang paling jelas dan paling dekat dengan barcode resmi PLN.

            VALID jika:
            - Ada tampilan meter listrik
            - Ada nilai kWh
            - Ada barcode/nomor meter resmi

            Jika barcode tidak terbaca tetapi meter jelas terlihat:
            - tetap valid=true
            - isi barcode=null
            - isi message penjelasan singkat

            Kembalikan HANYA JSON valid tanpa markdown. nomor_token isi dengan id pelanggan biasannya tulisan warna putih besar. nomor_meter sama dengan barcode.

            Format:
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

            ATURAN OUTPUT:
            - barcode = nomor barcode resmi di bawah LCD meter
            - nomor_meter = sama dengan barcode
            - nomor_token = angka tulisan tangan besar warna putih
            - Semua nomor hanya boleh berisi angka
            - Jangan tambahkan spasi
            - Jangan tambahkan tanda "-"
            - Jangan mengarang data

            Jika gambar bukan meter listrik/token listrik:
            {
                "valid": false,
                "message": "Gambar tidak sesuai. Meter listrik/token listrik tidak ditemukan.",
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

            Fokus utama:
            - Baca serial number mesin.
            - Baca nama / tipe mesin jika terlihat.
            - Baca tanggal dan lokasi jika ada watermark / tulisan pada gambar.
            - Baca semua counter berdasarkan kode counter.

            Syarat valid:
            - Harus terlihat serial number mesin.
            - Harus terlihat minimal salah satu counter Black & White atau Full Color.
            - Jika serial number tidak ada, kembalikan valid false.

            Mapping counter Canon / mesin fotocopy:
            - Kode 112 Total (Black & White/Large) = bw_a3
            - Kode 113 Total (Black & White/Small) = bw_a4
            - Kode 122 Total (Full Color + Single Color/Large) = color_a3
            - Kode 123 Total (Full Color + Single Color/Small) = color_a4
            - Kode 471 Total (Long Sheet) = long_sheet_total
            - Kode 473 Total (Black & White/Long Sheet) = bw_long_sheet
            - Kode 475 Total (Full Color + Single Color/Long Sheet) = color_long_sheet
            - Kode 101 Total 1 = total_counter_mesin

            Aturan ukuran:
            - Large artinya A3.
            - Small artinya A4.
            - Long Sheet dihitung terpisah, tetapi untuk acuan harga nanti menggunakan harga A3.
            - Jangan gabungkan A3 dan A4.
            - Jangan gabungkan BW dan Color.
            - Semua angka harus integer.
            - Hilangkan nol di depan.
            - Jika ada counter yang tidak terlihat, isi 0.

            Mapping untuk billing:
            - bw_a3 akan dicocokkan dengan harga_bw_a3 pada master mesin.
            - bw_a4 akan dicocokkan dengan harga_bw_a4 pada master mesin.
            - color_a3 akan dicocokkan dengan harga_color_a3 pada master mesin.
            - color_a4 akan dicocokkan dengan harga_color_a4 pada master mesin.
            - bw_long_sheet dan color_long_sheet menggunakan harga A3 sesuai jenisnya.
            - total_long_sheet adalah counter total long sheet dari kode 471.
            - total_bw = bw_a3 + bw_a4.
            - total_color = color_a3 + color_a4.
            - total = total_bw + total_color + total_long_sheet.

            Contoh pembacaan:
            - 112 Total (Black & White/Large) bernilai 00027624 maka bw_a3 = 27624.
            - 113 Total (Black & White/Small) bernilai 00033680 maka bw_a4 = 33680.
            - 122 Total (Full Color + Single Color/Large) bernilai 01047569 maka color_a3 = 1047569.
            - 123 Total (Full Color + Single Color/Small) bernilai 00616046 maka color_a4 = 616046.
            - 471 Total (Long Sheet) bernilai 00000094 maka total_long_sheet = 94.
            - 473 Total (Black & White/Long Sheet) bernilai 00000000 maka bw_long_sheet = 0.
            - 475 Total (Full Color + Single Color/Long Sheet) bernilai 00000094 maka color_long_sheet = 94.
            - 101 Total 1 bernilai 01724919 maka total_counter_mesin = 1724919.

            Kembalikan hanya JSON valid:
            {
                "valid": true,
                "message": "",
                "data_penting": {
                    "tanggal": "1 Maret 2026",
                    "lokasi": "Tebet",
                    "nama_mesin": "iPR C710",
                    "serial_number": "2NT02555",

                    "bw_a3": 0,
                    "bw_a4": 0,
                    "color_a3": 0,
                    "color_a4": 0,

                    "total_long_sheet": 0,
                    "bw_long_sheet": 0,
                    "color_long_sheet": 0,

                    "total_bw": 0,
                    "total_color": 0,
                    "total_counter_mesin": 0,
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

    private static function partMaintenancePrompt(): string
    {
        return '
            Anda adalah AI OCR untuk menganalisis gambar biaya part atau maintenance mesin.

            Konteks:
            - User sudah memilih menu di WhatsApp.
            - User sudah memilih mesin atau part dari sistem.
            - User hanya mengirim gambar bukti dan mengetik nominal di chat WhatsApp.
            - Jadi tugas Anda hanya membaca isi gambar secara umum dan mendeskripsikan gambar.

            Tugas utama:
            - Tentukan apakah gambar adalah bukti yang relevan.
            - Gambar bisa berupa:
            1. foto part mesin
            2. foto kerusakan mesin
            3. foto maintenance/perbaikan mesin
            4. struk pembayaran
            5. nota pembelian
            6. invoice
            7. bukti transfer
            8. bukti pembayaran

            Data yang perlu diambil:
            - jenis_gambar
            - deskripsi_gambar
            - tanggal jika terlihat
            - nama_toko/vendor jika terlihat
            - nomor_nota/invoice/referensi jika terlihat
            - nominal yang terlihat di gambar jika ada

            PENTING:
            - Nominal dapat berasal dari gambar atau dari chat WhatsApp user.
            - Jika nominal di gambar tidak terlihat, isi nominal_gambar = null.
            - Jika nominal terlihat, ubah menjadi integer.
            - Hapus Rp, IDR, titik, koma, spasi, dan desimal.
            - Contoh "Rp 1.250.000" menjadi 1250000.
            - Jangan mengarang data.
            - Jika data tidak terlihat, isi null.
            - Kembalikan hanya JSON valid tanpa markdown.

            Format JSON wajib:
            {
                "valid": true,
                "message": "",
                "data_penting": {
                    "jenis_gambar": null,
                    "deskripsi_gambar": null,
                    "tanggal": null,
                    "nama_toko_atau_vendor": null,
                    "nomor_nota_invoice_referensi": null,
                    "nominal_chat": null,
                    "nominal_gambar": null,
                    "catatan": null
                }
            }

            Contoh deskripsi_gambar:
            - "Foto part drum mesin fotocopy."
            - "Foto kondisi mesin sedang dibongkar untuk maintenance."
            - "Nota pembelian sparepart mesin."
            - "Bukti transfer pembayaran part mesin."
            - "Invoice jasa service mesin."

            Syarat valid:
            - Gambar masih berhubungan dengan part, maintenance, struk, nota, invoice, atau bukti pembayaran.
            - Minimal ada objek/bukti yang bisa dijelaskan.

            Jika gambar tidak sesuai:
            {
                "valid": false,
                "message": "Gambar tidak sesuai. Gambar bukan foto part, maintenance, struk, nota, invoice, atau bukti pembayaran.",
                "data_penting": {}
            }
        ';
    }

    private static function ceaPrompt(): string
    {
        return '
        Analisis gambar counter mesin CEA.

        Fokus:
        - Serial Number mesin.
        - Nama / tipe mesin.
        - Counter 101 Total 1.
        - Counter 301 Print.
        - Counter 201 Copy.
        - Lokasi jika terdapat watermark.
        - Tanggal jika ada.

        Aturan:
        - Semua counter harus integer.
        - Hilangkan nol di depan.
        - Jika counter tidak ditemukan isi 0.
        - Jangan mengarang data.

        Mapping Counter:

        101 = total_counter_mesin
        301 = print_counter
        201 = copy_counter

        Valid jika:
        - Serial Number ditemukan.
        - Minimal satu counter ditemukan.

        Kembalikan HANYA JSON:

        {
            "valid": true,
            "message": "",
            "data_penting": {
                "tanggal": null,
                "lokasi": null,

                "nama_mesin": null,
                "serial_number": null,

                "total_counter_mesin": 0,
                "print_counter": 0,
                "copy_counter": 0
            }
        }

        Contoh:

        Jika terlihat:

        101 Total 1 = 01584909
        301 Print = 00915569
        201 Copy = 00669304

        maka hasil:

        {
            "valid": true,
            "message": "",
            "data_penting": {
                "nama_mesin": "iR-ADV 6575",
                "serial_number": "SMT01092",
                "total_counter_mesin": 1584909,
                "print_counter": 915569,
                "copy_counter": 669304
            }
        }

        Jika tidak ditemukan serial number:

        {
            "valid": false,
            "message": "Serial number atau counter mesin tidak ditemukan.",
            "data_penting": {}
        }
        ';
    }

    private static function asabaPrompt(): string
    {
        return '
        Analisis gambar counter mesin Develop / Asaba.

        Fokus:
        - Serial Number mesin.
        - Nama mesin jika terlihat.
        - Total Counter.
        - Printer Total Counter.
        - Copy Total Counter.
        - Scan Total Counter.
        - Feed Paper Counter.
        - Output Paper Counter.
        - Lokasi jika ada watermark.
        - Tanggal jika ada.

        Aturan:
        - Semua counter harus integer.
        - Hilangkan nol di depan.
        - Jika tidak ditemukan isi 0.
        - Jangan mengarang data.

        Mapping:

        Total Counter = total_counter
        Printer Total Counter = printer_counter
        Copy Total Counter = copy_counter
        Scan Total Counter = scan_counter
        Feed Paper Counter = feed_paper_counter
        Output Paper Counter = output_paper_counter

        Valid jika:
        - Serial Number ditemukan.
        - Minimal satu counter ditemukan.

        Kembalikan HANYA JSON:

        {
            "valid": true,
            "message": "",
            "data_penting": {
                "tanggal": null,
                "lokasi": null,

                "nama_mesin": null,
                "serial_number": null,

                "total_counter": 0,
                "printer_counter": 0,
                "copy_counter": 0,
                "scan_counter": 0,
                "feed_paper_counter": 0,
                "output_paper_counter": 0
            }
        }

        Contoh:

        Total Counter = 00250121
        Printer Total Counter = 000178547
        Copy Total Counter = 00071574
        Scan Total Counter = 00002311
        Feed Paper Counter = 00202800
        Output Paper Counter = 00202276

        Maka:

        {
            "valid": true,
            "message": "",
            "data_penting": {
                "serial_number": "ADF2W41000009",
                "total_counter": 250121,
                "printer_counter": 178547,
                "copy_counter": 71574,
                "scan_counter": 2311,
                "feed_paper_counter": 202800,
                "output_paper_counter": 202276
            }
        }

        Jika serial number tidak ditemukan:

        {
            "valid": false,
            "message": "Serial number atau counter mesin tidak ditemukan.",
            "data_penting": {}
        }
        ';
    }
}