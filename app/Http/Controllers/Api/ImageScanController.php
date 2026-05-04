<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeImageJob;
use App\Models\ImageScan;
use Illuminate\Http\Request;

class ImageScanController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $request->file('image');

        $path = $file->store('image-scans', 'public');

        $scan = ImageScan::create([
            'image_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'status' => 'pending',
        ]);

        AnalyzeImageJob::dispatch($scan->id);

        return response()->json([
            'success' => true,
            'message' => 'Gambar berhasil diupload dan sedang dianalisis.',
            'data' => $scan,
        ]);
    }

    public function index()
    {
        $scans = ImageScan::all();

        return response()->json([
            'success' => true,
            'data' => $scans,
        ]);
    }

    public function show($id)
    {
        $scan = ImageScan::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $scan,
        ]);
    }
}