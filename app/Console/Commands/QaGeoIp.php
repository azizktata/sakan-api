<?php

namespace App\Console\Commands;

use App\Services\GeoIpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QaGeoIp extends Command
{
    protected $signature = 'qa:geoip';
    protected $description = 'QA: test GeoIpService with real IPs and insert synthetic view rows';

    private array $testIps = [
        '197.0.0.1'   => ['expect_country' => 'Tunisia',       'expect_city' => 'Sfax'],
        '41.224.0.1'  => ['expect_country' => 'Tunisia',       'expect_city' => 'Fouchana'],
        '8.8.8.8'     => ['expect_country' => 'United States', 'expect_city' => null],
        '105.101.0.1' => ['expect_country' => 'Algeria',       'expect_city' => 'Algiers'],
        '127.0.0.1'   => ['expect_country' => null,            'expect_city' => null],
    ];

    public function handle(): int
    {
        $this->info('=== GeoIP QA ===');
        $this->newLine();

        $propertyId = DB::table('properties')->value('id');
        if (! $propertyId) {
            $this->error('No properties found — seed the DB first.');
            return 1;
        }

        $allPassed = true;

        foreach ($this->testIps as $ip => $expected) {
            $geo = GeoIpService::lookup($ip);

            $countryOk = $geo['country'] === $expected['expect_country'];
            $cityOk    = $geo['city']    === $expected['expect_city'];
            $pass      = $countryOk && $cityOk;

            if (! $pass) {
                $allPassed = false;
            }

            $status = $pass ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';

            $this->line(sprintf(
                '%s  %-16s  country=%-20s city=%-15s',
                $status,
                $ip,
                ($geo['country'] ?? 'null') . ($countryOk ? '' : " (expected: {$expected['expect_country']})"),
                ($geo['city'] ?? 'null') . ($cityOk ? '' : " (expected: " . ($expected['expect_city'] ?? 'null') . ")")
            ));

            // Insert a synthetic view row so geo-breakdown endpoint has data
            if ($geo['country'] !== null) {
                $visitorKey  = (string) Str::uuid();
                $sessionBucket = now()->format('Y-m-d H:00');
                $uniqueKey   = hash('sha256', $propertyId . $visitorKey . $sessionBucket . $ip);
                DB::table('property_views')->insertOrIgnore([
                    'property_id'    => $propertyId,
                    'visitor_key'    => $visitorKey,
                    'session_bucket' => $sessionBucket,
                    'unique_key'     => $uniqueKey,
                    'source'         => 'direct',
                    'device'         => 'desktop',
                    'ip_hash'        => hash('sha256', $ip),
                    'view_id'        => (string) Str::uuid(),
                    'country'        => $geo['country'],
                    'city_geo'       => $geo['city'],
                    'created_at'     => now(),
                ]);
                $this->line("         → Inserted synthetic view row: country={$geo['country']}, city_geo=" . ($geo['city'] ?? 'null'));
            }
        }

        $this->newLine();
        if ($allPassed) {
            $this->info('All GeoIP assertions PASSED.');
        } else {
            $this->error('Some GeoIP assertions FAILED — see above.');
        }

        $this->newLine();
        $this->info('property_views with geo data:');
        $rows = DB::table('property_views')
            ->whereNotNull('country')
            ->select('country', 'city_geo', 'source', 'device')
            ->get();
        $this->table(['country', 'city_geo', 'source', 'device'], $rows->map(fn($r) => (array) $r)->toArray());

        return $allPassed ? 0 : 1;
    }
}
