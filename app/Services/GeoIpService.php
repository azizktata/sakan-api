<?php

namespace App\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Throwable;

class GeoIpService
{
    private static ?Reader $reader = null;

    public static function lookup(string $ip): array
    {
        try {
            $reader = self::getReader();
            if ($reader === null) {
                return ['country' => null, 'city' => null];
            }
            $record = $reader->city($ip);
            return [
                'country' => $record->country->name ?: null,
                'city'    => $record->city->name ?: null,
            ];
        } catch (AddressNotFoundException) {
            return ['country' => null, 'city' => null];
        } catch (Throwable) {
            return ['country' => null, 'city' => null];
        }
    }

    private static function getReader(): ?Reader
    {
        if (self::$reader !== null) {
            return self::$reader;
        }
        $path = storage_path('geoip/GeoLite2-City.mmdb');
        if (!file_exists($path)) {
            return null;
        }
        self::$reader = new Reader($path);
        return self::$reader;
    }
}
