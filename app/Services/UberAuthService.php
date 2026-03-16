<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UberAuthService
{
    public function getAccessToken(): string
    {
        // Test mode a fake token return 
        if (config('services.uber_direct.test_mode')) {
            return 'fake-test-token-for-development';
        }

        return Cache::remember('uber_direct_access_token', now()->addDays(25), function () {
            Log::info('Fetching new Uber Direct access token');

            $response = Http::asForm()->post('https://auth.uber.com/oauth/v2/token', [
                'client_id'     => config('services.uber_direct.client_id'),
                'client_secret' => config('services.uber_direct.client_secret'),
                'grant_type'    => 'client_credentials',
                'scope'         => 'eats.deliveries',
            ]);

            if ($response->failed()) {
                Log::error('Failed to fetch Uber access token', [
                    'response' => $response->body(),
                ]);
                throw new \RuntimeException('Uber auth failed: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }
}