<?php

namespace App\Mail;

use App\Models\MasterVendor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class VendorMesinMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MasterVendor $vendor,
        public Collection $mesins
    ) {}

    public function build()
    {
        return $this->subject('Daftar Mesin Vendor - ' . $this->vendor->nama_vendor)
            ->view('emails.vendor-mesin');
    }
}