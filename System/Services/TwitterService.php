<?php

namespace App\Extensions\AISocialMedia\System\Services;

use App\Extensions\AISocialMedia\System\Services\Contracts\BaseService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Noweh\TwitterApi\Client;
use Exception;

class TwitterService extends BaseService
{
    public function share($text): void
    {
        $post = $this->getPost();

        $setting = $this->getPlatform()->setting;

        if (! $setting) {
            throw new \Exception('Twitter platform is not configured. Please connect your Twitter account first.');
        }

        // Check if credentials are expired
        if ($setting->expires_at && $setting->expires_at->isPast()) {
            throw new \Exception('Twitter credentials have expired. Please reconnect your Twitter account.');
        }

        try {
            $credentials = new Fluent($setting['credentials'] ?: []);

            $credentials = Arr::only($credentials->toArray(), [
                'account_id',
                'access_token',
                'access_token_secret',
                'consumer_key',
                'consumer_secret',
                'bearer_token',
            ]);

            if (empty($credentials['access_token']) || empty($credentials['access_token_secret'])) {
                throw new \Exception('Twitter access tokens are missing. Please reconnect your account.');
            }

            $client = new Client($credentials);
            $client->tweet()->create()
                ->performRequest([
                    'text' => str_replace('"', '', $text),
                ],
                    withHeaders: true
                );

            $post->update(['last_run_date' => now()]);
        } catch (Exception $e) {
            Log::error('TwitterService share failed', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception('Failed to post to Twitter: ' . $e->getMessage() . '. Please check your connection and try again.');
        }
    }
}
