<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1f2937;">
    <h2>Daftar Mesin Vendor</h2>

    <p>Yth. {{ $vendor->nama_vendor }},</p>

    <p>Berikut adalah daftar mesin yang terdaftar atas vendor Anda:</p>

    @foreach ($mesins as $mesin)
        <div style="border:1px solid #ddd; border-radius:10px; padding:16px; margin-bottom:16px;">
            <h3 style="margin-top:0;">{{ $mesin->nama_mesin }}</h3>
            
            <p><strong>Nama PT:</strong> {{ $mesin->cabang?->nama_pt ?? '-' }}</p>
            <p><strong>Cabang:</strong> {{ $mesin->cabang?->nama_cabang ?? '-' }}</p>
            <p><strong>Serial Number:</strong> {{ $mesin->serial_number ?? '-' }}</p>

        </div>
    @endforeach

    <p>Terima kasih.</p>
</body>
</html>