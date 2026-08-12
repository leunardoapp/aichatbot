<?php

namespace App\Extensions\AISocialMedia\System\Services;

use App\Extensions\AISocialMedia\System\Services\Contracts\BaseService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class LinkedInService extends BaseService
{
    public string $accessToken;

    public function getProfile()
    {
        try {
            $client = new Client;

            $response = $client->request('GET', 'https://api.linkedin.com/v2/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                ],
            ]);

            $object = json_decode($response->getBody()->getContents(), true);

            return $object;
        } catch (RequestException $e) {
            Log::error('LinkedInService getProfile failed', [
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse()?->getStatusCode(),
            ]);
            throw new \Exception('Failed to fetch LinkedIn profile: ' . $e->getMessage());
        } catch (Exception $e) {
            Log::error('LinkedInService getProfile failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function shareNone($text): void
    {
        $post = $this->getPost();
        
        try {
            // Check if credentials are expired
            $setting = $this->getPlatform()->setting;
            if ($setting && $setting->expires_at && $setting->expires_at->isPast()) {
                throw new \Exception('LinkedIn credentials have expired. Please reconnect your account.');
            }
            
            $client = new Client;

            $profile = $this->getProfile();

            if (empty($profile)) {
                throw new \Exception('Unable to retrieve LinkedIn profile. Please reconnect your account.');
            }

            if (! isset($profile['sub'])) {
                throw new \Exception('LinkedIn profile information is incomplete. Please reconnect your account.');
            }

            $personURN = $profile['sub'];

            $client->request('POST', 'https://api.linkedin.com/v2/ugcPosts', [
                'headers' => [
                    'Authorization'             => 'Bearer ' . $this->accessToken,
                    'Connection'                => 'Keep-Alive',
                    'Content-Type'              => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ],
                'json' => [
                    'author'          => 'urn:li:person:' . $personURN,
                    'lifecycleState'  => 'PUBLISHED',
                    'specificContent' => [
                        'com.linkedin.ugc.ShareContent' => [
                            'shareCommentary' => [
                                'text' => $text,
                            ],
                            'shareMediaCategory' => 'NONE',
                        ],
                    ],
                    'visibility' => [
                        'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                    ],
                ],
            ]);

            $post->update(['last_run_date' => now()]);
        } catch (RequestException $e) {
            Log::error('LinkedInService shareNone failed', [
                'post_id' => $post->id ?? null,
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse()?->getStatusCode(),
            ]);
            throw new \Exception('Failed to post to LinkedIn: ' . $e->getMessage() . '. Please check your connection and try again.');
        } catch (Exception $e) {
            Log::error('LinkedInService shareNone failed', [
                'post_id' => $post->id ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function share($text): void
    {
        $setting = $this->getPlatform()->setting;
        
        if (! $setting) {
            throw new \Exception('LinkedIn platform is not configured. Please connect your LinkedIn account first.');
        }
        
        // Check if credentials are expired
        if ($setting->expires_at && $setting->expires_at->isPast()) {
            throw new \Exception('LinkedIn credentials have expired. Please reconnect your account.');
        }
        
        $this->accessToken = $setting->getCredentialValue('access_token');
        
        if (empty($this->accessToken)) {
            throw new \Exception('LinkedIn access token is missing. Please reconnect your account.');
        }

        if (! empty($text)) {
            $content = preg_replace('/\n+/', "\n", $text);

            $this->shareNone($content);
        }
    }
}
