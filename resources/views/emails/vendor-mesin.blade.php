<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2>Daftar Mesin Vendor</h2>

    <p>Yth. {{ $vendor->nama_vendor }},</p>

    <p>Berikut adalah daftar mesin yang terdaftar atas vendor Anda:</p>

    @foreach ($mesins as $mesin)
        <div style="border:1px solid #ddd; border-radius:10px; padding:16px; margin-bottom:16px;">
            <h3 style="margin-top:0;">{{ $mesin->nama_mesin }}</h3>

            <p><strong>Cabang:</strong> {{ $mesin->cabang?->nama_cabang ?? '-' }}</p>
            <p><strong>Alamat Cabang:</strong> {{ $mesin->cabang?->alamat ?? '-' }}</p>
            <p><strong>Merk:</strong> {{ $mesin->merk ?? '-' }}</p>
            <p><strong>Tipe:</strong> {{ $mesin->tipe ?? '-' }}</p>
            <p><strong>Serial Number:</strong> {{ $mesin->serial_number ?? '-' }}</p>
            <p><strong>Status Kepemilikan:</strong> {{ $mesin->status_kepemilikan ?? '-' }}</p>

            <hr>

            <p><strong>Minimum Charge Size:</strong> {{ $mesin->minimum_charge_size ?? '-' }}</p>
            <p><strong>Minimum Charge Click:</strong> {{ number_format((float) $mesin->minimum_charge_click, 0, ',', '.') }}</p>
            <p><strong>Minimum Charge Nominal:</strong> Rp {{ number_format((float) $mesin->minimum_charge_nominal, 0, ',', '.') }}</p>

            <hr>

            <p><strong>Harga Color A3:</strong> Rp {{ number_format((float) $mesin->harga_color_a3, 0, ',', '.') }}</p>
            <p><strong>Harga Color A4:</strong> Rp {{ number_format((float) $mesin->harga_color_a4, 0, ',', '.') }}</p>
            <p><strong>Harga BW A3:</strong> Rp {{ number_format((float) $mesin->harga_bw_a3, 0, ',', '.') }}</p>
            <p><strong>Harga BW A4:</strong> Rp {{ number_format((float) $mesin->harga_bw_a4, 0, ',', '.') }}</p>

            <hr>

            <p><strong>Over Click Color A3:</strong> Rp {{ number_format((float) $mesin->over_click_color_a3, 0, ',', '.') }}</p>
            <p><strong>Over Click Color A4:</strong> Rp {{ number_format((float) $mesin->over_click_color_a4, 0, ',', '.') }}</p>
            <p><strong>Over Click BW A3:</strong> Rp {{ number_format((float) $mesin->over_click_bw_a3, 0, ',', '.') }}</p>
            <p><strong>Over Click BW A4:</strong> Rp {{ number_format((float) $mesin->over_click_bw_a4, 0, ',', '.') }}</p>

            <p><strong>Free Klik:</strong> {{ $mesin->free_klik_percent ?? 0 }}%</p>
            <p><strong>Keterangan:</strong> {{ $mesin->keterangan ?? '-' }}</p>
        </div>
    @endforeach

    <p>Terima kasih.</p>
</body>
</html>