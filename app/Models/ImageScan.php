<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageScan extends Model
{
    protected $fillable = [
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
}