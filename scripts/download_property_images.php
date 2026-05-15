<?php

/**
 * Downloads property images from external sources to public/uploads/properties/
 * and updates DB URLs to match the site's own upload format.
 *
 * Usage:
 *   php scripts/download_property_images.php           # all images
 *   php scripts/download_property_images.php --limit=5 # test with 5 images
 *   php scripts/download_property_images.php --dry-run  # preview only
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PropertyImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// ── CLI args ──────────────────────────────────────────────────────────────────
$limit  = null;
$dryRun = false;
foreach ($argv as $arg) {
    if (preg_match('/--limit=(\d+)/', $arg, $m)) $limit  = (int) $m[1];
    if ($arg === '--dry-run')                      $dryRun = true;
}

// ── Fetch target rows ─────────────────────────────────────────────────────────
$query = PropertyImage::where('url', 'like', '%tunisie-annonce.com%')
    ->orWhere('url', 'like', '%mubawab%');

if ($limit) $query->limit($limit);

$images = $query->get();
echo "Found {$images->count()} images to process" . ($dryRun ? ' (dry-run)' : '') . PHP_EOL;

$ok = 0; $skip = 0; $fail = 0;

foreach ($images as $img) {
    $sourceUrl = $img->url;
    $ext       = strtolower(pathinfo(parse_url($sourceUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
    $filename  = Str::uuid() . '.' . $ext;
    $localPath = 'properties/' . $filename;

    if ($dryRun) {
        echo "  WOULD DL  #{$img->id}  {$sourceUrl}" . PHP_EOL;
        $ok++;
        continue;
    }

    // Download via cURL
    $ch = curl_init($sourceUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SAKAN/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $bytes = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($bytes === false || strlen($bytes) < 1000 || $httpCode >= 400) {
        echo "  FAIL  #{$img->id}  HTTP {$httpCode}  {$curlErr}  {$sourceUrl}" . PHP_EOL;
        $fail++;
        continue;
    }

    Storage::disk('uploads')->put($localPath, $bytes);

    $img->url = Storage::disk('uploads')->url($localPath);
    $img->save();

    echo "  OK    #{$img->id}  {$filename}  (" . round(strlen($bytes) / 1024) . " KB)  was: " . basename($sourceUrl) . PHP_EOL;
    $ok++;

    usleep(300000); // 0.3s polite delay
}

echo PHP_EOL . "Done — OK: {$ok}  Skipped: {$skip}  Failed: {$fail}" . PHP_EOL;
