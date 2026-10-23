<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';

    /**
     * Ubah koordinat jadi alamat lewat Nominatim (OpenStreetMap), gratis
     * dan tanpa API key. Gagal diam-diam (null) supaya tidak pernah
     * menggagalkan proses absen/kunjungan hanya karena geocoding down.
     */
    public function reverseGeocode(?float $lat, ?float $lng): ?string
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.name') . ' (' . config('app.url') . ')',
            ])
                ->timeout(5)
                ->get(self::ENDPOINT, [
                    'lat'    => $lat,
                    'lon'    => $lng,
                    'format' => 'jsonv2',
                    'zoom'   => 18,
                ]);

            if (!$response->successful()) {
                return null;
            }

            return $response->json('display_name');
        } catch (\Throwable $e) {
            Log::warning('Reverse geocoding gagal', [
                'lat' => $lat, 'lng' => $lng, 'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
