<?php

namespace App\Extensions\AISocialMedia\System\Jobs;

use App\Domains\Entity\Enums\EntityEnum;
use App\Domains\Entity\Facades\Entity;
use App\Extensions\AISocialMedia\System\Models\ScheduledPost;
use App\Extensions\AISocialMedia\System\Services\AutomationService;
use App\Extensions\AISocialMedia\System\Services\Contracts\BaseService;
use App\Helpers\Classes\ApiHelper;
use App\Models\Setting;
use App\Models\Usage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI as FacadesOpenAI;
use RuntimeException;
use Throwable;

class UserPostJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected ScheduledPost $post;

    public function __construct(ScheduledPost $post)
    {
        $this->post = $post;
    }

    public function handle(): void
    {
        try {
            $service = $this->getService($this->post);
            
            // Check if platform credentials are expired before attempting to share
            $platform = $service->getPlatform();
            if ($platform && $platform->setting) {
                $setting = $platform->setting;
                if ($setting->expires_at && $setting->expires_at->isPast()) {
                    throw new \Exception('Platform credentials have expired. Please reconnect your ' . $platform->name . ' account.');
                }
            }
            
            $service->share(
                $this->content($this->post)
            );
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('UserPostJob failed for post ID ' . $this->post->id, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'platform' => $this->post->platform,
                'user_id' => $this->post->user_id,
            ]);
            
            // Re-throw with more context
            throw new \Exception(
                'Failed to publish post: ' . $e->getMessage() . 
                '. Please check your platform connection and try again.'
            );
        }
    }

    public function content(ScheduledPost $post): ?string
    {
        if ($post->auto_generate) {
            $setting = Setting::getCache();

            ApiHelper::setOpenAiKey();
            // create the completion
            $completion = FacadesOpenAI::chat()->create([
                'model'    => $setting->openai_default_model,
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => $this->post->prompt,
                    ],
                ],
            ]);

            $driver = Entity::driver(
                EntityEnum::tryFrom($setting->openai_default_model)
            )->forUser($this->post->user);

            $driver->input($completion->choices[0]->message->content)->calculateCredit();

            $driver->redirectIfNoCreditBalance();

            $driver->decreaseCredit();
            Usage::getSingle()->updateWordCounts($driver->calculate());

            return $completion->choices[0]->message->content;
        }

        return $post->getAttribute('content');
    }

    public function getService(ScheduledPost $post): BaseService
    {
        $platform = AutomationService::find($post->platform);

        $service = $platform?->service;

        if ($service instanceof BaseService) {
            return $service->setPlatform($platform)->setPost($post);
        }

        throw new RuntimeException('Service not found');
    }
}
