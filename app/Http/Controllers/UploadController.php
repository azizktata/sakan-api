<?php

namespace App\Http\Controllers;

use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function presign(Request $request): JsonResponse
    {
        $request->validate([
            'filename'    => 'required|string|max:255',
            'contentType' => 'required|in:image/webp,image/jpeg,image/png',
        ]);

        $key = 'properties/' . uniqid() . '-' . $request->filename;

        $s3 = new S3Client([
            'version'  => 'latest',
            'region'   => config('filesystems.disks.r2.region', 'auto'),
            'endpoint' => config('filesystems.disks.r2.endpoint'),
            'credentials' => [
                'key'    => config('filesystems.disks.r2.key'),
                'secret' => config('filesystems.disks.r2.secret'),
            ],
            'use_path_style_endpoint' => true,
        ]);

        $cmd = $s3->getCommand('PutObject', [
            'Bucket'      => config('filesystems.disks.r2.bucket'),
            'Key'         => $key,
            'ContentType' => $request->contentType,
        ]);

        $presigned = $s3->createPresignedRequest($cmd, '+15 minutes');
        $publicUrl = config('filesystems.disks.r2.url') . '/' . $key;

        return response()->json([
            'signedUrl' => (string) $presigned->getUri(),
            'publicUrl' => $publicUrl,
        ]);
    }
}
