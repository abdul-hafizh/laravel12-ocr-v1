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
            - Baca angka hanya jika benar-benar terlihat jelas.
            - Fokus utama pada:
            1. nilai kWh di layar LCD
            2. barcode / nomor meter resmi yang berada di bawah layar kWh
            3. nomor token / ID pelanggan jika ada
            4. tanggal pada foto
            5. lokasi/alamat overlay pada foto jika ada

            ATURAN PALING PENTING:
            - JANGAN mengarang angka.
            - JANGAN menebak angka yang buram, redup, tertutup pantulan cahaya, atau tidak terbaca utuh.
            - Jika nilai kWh di layar LCD tidak terlihat jelas sampai digit terakhir, maka valid=false.
            - Jika nilai kWh hanya terlihat sebagian, maka valid=false.
            - Jika ragu antara dua angka, maka valid=false.
            - Data hanya valid jika kWh terbaca jelas dan lengkap.

            ATURAN KWH:
            - Nilai kWh harus berasal dari layar LCD meter.
            - kWh wajib berformat angka desimal dengan 2 digit setelah titik.
            - Contoh format benar:
                1321.34
                3022.48
                1006.52
                2462.61
                3138.30
            - Jika layar menampilkan koma, ubah menjadi titik.
            - Jika layar menampilkan 3138.3, ubah menjadi 3138.30.
            - Jika layar menampilkan angka tanpa desimal dan tidak jelas digit desimalnya, maka valid=false.
            - Jangan mengambil angka selain dari LCD sebagai kWh.

            ATURAN BARCODE / NOMOR METER:
            - Barcode/nomor meter biasanya berupa angka panjang di bawah layar kWh.
            - Ambil angka yang berada tepat di bawah barcode resmi PLN.
            - Jangan mengambil angka tulisan tangan putih besar pada cover meter jika ada barcode resmi.
            - Hilangkan spasi saat menyimpan barcode dan nomor meter.
            - Jika barcode terlihat seperti:
            "32 9027 2726 5"
            maka simpan menjadi:
            "32902727265"

            ATURAN NOMOR TOKEN / ID PELANGGAN:
            - nomor_token diisi dengan angka tulisan tangan besar warna putih jika ada.
            - Semua nomor hanya boleh berisi angka.
            - Jangan tambahkan spasi.
            - Jangan tambahkan tanda "-".

            VALID jika:
            - Gambar adalah meter listrik/token listrik.
            - Nilai kWh di layar LCD terbaca jelas, lengkap, dan memiliki 2 digit desimal.
            - Barcode/nomor meter resmi terbaca, atau meter jelas terlihat tetapi barcode tidak terbaca.

            Jika barcode tidak terbaca tetapi kWh terlihat jelas:
            - tetap valid=true
            - isi barcode=null
            - isi nomor_meter=null
            - isi message penjelasan singkat

            Jika kWh tidak terbaca jelas:
            {
                "valid": false,
                "message": "Nilai kWh pada layar LCD tidak terbaca jelas. Foto tidak disimpan.",
                "data_penting": {}
            }

            Kembalikan HANYA JSON valid tanpa markdown.

            Format valid:
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
            - kwh wajib string/angka format 0.00
            - Semua nomor hanya boleh berisi angka
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
        - Baca nama / tipe mesin.
        - Baca lokasi dan tanggal jika ada watermark atau tulisan pada gambar.
        - Baca counter berdasarkan LABEL counter, bukan berdasarkan kode angka.

        Syarat valid:
        - Serial number harus ditemukan.
        - Minimal ditemukan counter Black & White atau Full Color.
        - Jika serial number tidak ditemukan maka valid = false.

        =====================================================
        ATURAN PENTING
        =====================================================

        - Jangan menentukan counter dari kode angka sebelah kiri.
        - Tentukan counter dari teks label di sebelah kode.
        - Kata "Copy + Print" boleh diabaikan.
        - Label "Copy + Print (Full Color/Large)" artinya sama dengan "Total (Full Color + Single Color/Large)".
        - Label "Copy + Print (Full Color/Small)" artinya sama dengan "Total (Full Color + Single Color/Small)".
        - Full Color, Single Color, dan Color termasuk kategori COLOR.
        - Large artinya A3.
        - Small artinya A4.
        - Semua angka harus integer.
        - Hilangkan nol di depan.
        - Jika counter tidak ditemukan isi 0.
        - Jangan mengarang nilai.

        =====================================================
        IDENTIFIKASI COUNTER BERDASARKAN LABEL
        =====================================================

        bw_a3:
        Ambil nilai jika label mengandung:
        - Black & White/Large
        - B&W/Large
        - Mono/Large

        bw_a4:
        Ambil nilai jika label mengandung:
        - Black & White/Small
        - B&W/Small
        - Mono/Small

        color_a3:
        Ambil nilai jika label mengandung:
        - Full Color/Large
        - Full Color + Single Color/Large
        - Single Color/Large
        - Color/Large
        - Copy + Print (Full Color/Large)
        - Copy + Print (Color/Large)

        color_a4:
        Ambil nilai jika label mengandung:
        - Full Color/Small
        - Full Color + Single Color/Small
        - Single Color/Small
        - Color/Small
        - Copy + Print (Full Color/Small)
        - Copy + Print (Color/Small)

        total_long_sheet:
        Ambil nilai hanya jika label mengandung:
        - Total (Long Sheet)
        - Total Long Sheet
        - Long Sheet tanpa Black & White dan tanpa Full Color

        bw_long_sheet:
        Ambil nilai jika label mengandung:
        - Black & White/Long Sheet
        - B&W/Long Sheet
        - Mono/Long Sheet

        color_long_sheet:
        Ambil nilai jika label mengandung:
        - Full Color/Long Sheet
        - Full Color + Single Color/Long Sheet
        - Color/Long Sheet
        - Copy + Print (Full Color/Long Sheet)

        total_counter_mesin:
        Ambil nilai jika label mengandung:
        - Total 1
        - Grand Total
        - Total Counter

        =====================================================
        PERHITUNGAN
        =====================================================

        total_bw = bw_a3 + bw_a4
        total_color = color_a3 + color_a4
        total = total_bw + total_color

        =====================================================
        CONTOH WAJIB
        =====================================================

        Contoh 1:
        Label "Total (Full Color + Single Color/Large)" = 01047569
        maka color_a3 = 1047569

        Contoh 2:
        Label "Total (Full Color + Single Color/Small)" = 00616046
        maka color_a4 = 616046

        Contoh 3:
        Label "Copy + Print (Full Color/Large)" = 00092612
        maka color_a3 = 92612

        Contoh 4:
        Label "Copy + Print (Full Color/Small)" = 00545336
        maka color_a4 = 545336

        Contoh 5:
        Label "Total (Black & White/Large)" = 00003689
        maka bw_a3 = 3689

        Contoh 6:
        Label "Total (Black & White/Small)" = 00050543
        maka bw_a4 = 50543

        =====================================================
        OUTPUT JSON
        =====================================================

        {
            "valid": true,
            "message": "",
            "data_penting": {
                "tanggal": "",
                "lokasi": "",
                "nama_mesin": "",
                "serial_number": "",

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

        Jika tidak valid:
        {
            "valid": false,
            "message": "Serial number atau data counter mesin tidak ditemukan.",
            "data_penting": {}
        }

        Kembalikan HANYA JSON.
        ';
    }

    private static function partMaintenancePrompt(): string
    {
        return '
            Anda adalah AI OCR untuk memvalidasi dan mendeskripsikan gambar bukti biaya part atau maintenance mesin.

            Konteks:

            * User sudah memilih menu Part atau Maintenance di WhatsApp.
            * User sudah memilih mesin dari sistem.
            * Untuk menu Part, user mungkin sudah memilih part dari sistem.
            * User wajib mengirim foto sebagai bukti atau arsip.
            * Nominal biaya TIDAK berasal dari hasil OCR.
            * Nominal biaya akan diinput manual oleh user melalui chat WhatsApp setelah upload gambar.
            * Tugas Anda hanya memvalidasi dan mendeskripsikan isi gambar.

            Tugas utama:

            1. Tentukan apakah gambar masih relevan sebagai bukti biaya part atau maintenance mesin.
            2. Identifikasi jenis gambar.
            3. Buat deskripsi singkat mengenai isi gambar.
            4. Tambahkan catatan bila diperlukan.
            5. Jangan mengarang data yang tidak terlihat.

            Gambar yang dianggap valid:

            * Foto part mesin.
            * Foto sparepart mesin.
            * Foto kerusakan mesin.
            * Foto proses maintenance atau perbaikan mesin.
            * Foto teknisi sedang melakukan perbaikan mesin.
            * Nota pembelian sparepart.
            * Invoice jasa service.
            * Struk pembayaran.
            * Bukti transfer.
            * Bukti pembayaran lain yang berhubungan dengan mesin.

            Data yang perlu diambil:

            * jenis_gambar
            * deskripsi_gambar
            * catatan

            PENTING:

            * Jangan menghitung biaya.
            * Jangan menentukan nominal transaksi.
            * Jangan membaca atau memvalidasi nominal pembayaran.
            * Nominal biaya akan diproses dari input chat user.
            * Jika informasi tidak terlihat, isi dengan null.
            * Jangan mengarang data.
            * Kembalikan hanya JSON valid tanpa markdown.
            * Jangan memberikan penjelasan tambahan di luar JSON.

            Format JSON wajib:

            {
                "valid": true,
                "message": "",
                "data_penting": {
                    "jenis_gambar": null,
                    "deskripsi_gambar": null,
                    "catatan": null
                }
            }

            Contoh nilai jenis_gambar:

            * foto_part
            * foto_sparepart
            * foto_kerusakan
            * foto_maintenance
            * nota_pembelian
            * invoice
            * struk_pembayaran
            * bukti_transfer
            * bukti_pembayaran

            Contoh deskripsi_gambar:

            * "Foto part drum mesin fotocopy."
            * "Foto sparepart mesin yang akan diganti."
            * "Foto kondisi mesin sedang dibongkar untuk maintenance."
            * "Foto teknisi sedang melakukan perbaikan mesin."
            * "Nota pembelian sparepart mesin."
            * "Invoice jasa service mesin."
            * "Bukti transfer pembayaran service mesin."

            Contoh catatan:

            * "Bukti terlihat jelas dan relevan dengan maintenance mesin."
            * "Dokumen dapat digunakan sebagai arsip biaya part."
            * "Foto menunjukkan proses perbaikan mesin."

            Syarat valid:

            * Gambar masih berkaitan dengan mesin, part, maintenance, service, nota, invoice, struk, atau bukti pembayaran.
            * Minimal terdapat objek atau bukti yang dapat dijelaskan.

            Jika gambar tidak sesuai:

            {
            "valid": false,
            "message": "Gambar tidak sesuai. Silakan kirim foto part, maintenance, kerusakan mesin, nota, invoice, struk, atau bukti pembayaran yang terkait dengan mesin.",
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

        Beberapa tipe mesin juga memiliki:
        - Full Color Counter.
        - Single Color Counter.
        - Black Counter.

        Ambil semua counter yang terlihat.

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

        Full Color Counter = full_color_counter
        Single Color Counter = single_color_counter
        Black Counter = black_counter

        Valid jika:
        - Minimal satu counter ditemukan.
        - Jika serial number tidak ada tetapi counter terbaca dengan jelas,
        tetap dianggap valid.

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
                "output_paper_counter": 0,

                "full_color_counter": 0,
                "single_color_counter": 0,
                "black_counter": 0
            }
        }

        Contoh Tipe 1:

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
                "total_counter": 250121,
                "printer_counter": 178547,
                "copy_counter": 71574,
                "scan_counter": 2311,
                "feed_paper_counter": 202800,
                "output_paper_counter": 202276
            }
        }

        Contoh Tipe 2:

        Total Counter = 00506026
        Full Color Counter = 00443878
        Single Color Counter = 00000004
        Black Counter = 00062144
        Printer Total Counter = 00504964
        Copy Total Counter = 00001062
        Scan Total Counter = 00000244

        Maka:

        {
            "valid": true,
            "message": "",
            "data_penting": {
                "total_counter": 506026,
                "full_color_counter": 443878,
                "single_color_counter": 4,
                "black_counter": 62144,
                "printer_counter": 504964,
                "copy_counter": 1062,
                "scan_counter": 244
            }
        }

        Jika tidak ada counter yang berhasil dibaca:

        {
            "valid": false,
            "message": "Counter mesin tidak ditemukan.",
            "data_penting": {}
        }
        ';
    }
}