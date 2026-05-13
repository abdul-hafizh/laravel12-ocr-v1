<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageScan extends Model
{
    protected $fillable = [
        'user_id',
        'cabang_id',
        'scan_type',
        'image_path',
        'original_filename',
        'mime_type',
        'status',
        'analysis_result',
        'extracted_text',
        'error_message',
    ];

    protected $casts = [
        'analysis_result' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'cabang_id');
    }
}