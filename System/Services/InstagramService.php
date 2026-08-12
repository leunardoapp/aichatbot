<?php

namespace App\Extensions\AISocialMedia\System\Services;

use App\Extensions\AISocialMedia\System\Helpers\Instagram;
use App\Extensions\AISocialMedia\System\Services\Contracts\BaseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class InstagramService extends BaseService
{
    public function share($text): void
    {
        $post = $this->getPost();

        $platform = $this->getPlatform();

        if (! $setting = $platform->setting) {
            throw new \Exception('Instagram platform is not configured. Please connect your Instagram account first.');
        }

        // Check if credentials are expired
        if ($setting->expires_at && $setting->expires_at->isPast()) {
            throw new \Exception('Instagram credentials have expired. Please reconnect your account.');
        }

        $accessToken = $setting->getCredentialValue('access_token');

        $id = $setting->getCredentialValue('id');

        if (empty($accessToken)) {
            throw new \Exception('Instagram access token is missing. Please reconnect your account.');
        }

        if (empty($id)) {
            throw new \Exception('Instagram account ID is missing. Please reconnect your account.');
        }

        try {
            $postData = [
                'caption'   => $text,
                'image_url' => Storage::disk('public')->url('uploads/' . $post->media),
            ];

            // initialize the Instagram API class
            $instagram = new Instagram;
            // set the access token for the Instagram API class
            $instagram->setToken($accessToken);

            $instagram->publishSingleMediaPost($id, $postData);

            $post->update(['last_run_date' => now()]);
        } catch (Exception $e) {
            Log::error('InstagramService share failed', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw new \Exception('Failed to post to Instagram: ' . $e->getMessage() . '. Please check your connection and try again.');
        }
    }
}
