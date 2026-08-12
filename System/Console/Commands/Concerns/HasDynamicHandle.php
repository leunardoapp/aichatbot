<?php

namespace App\Extensions\AISocialMedia\System\Console\Commands\Concerns;

use App\Extensions\AISocialMedia\System\Jobs\UserPostJob;
use Exception;

trait HasDynamicHandle
{
    public function dynamicHandle(string $repeat_period): void
    {
        $posts = $this->query($repeat_period)->get();

        if ($posts->count()) {
            $this->query($repeat_period)->update(['command_running' => true, 'last_run_date'   => now()->toDateString()]);
        }

        foreach ($posts as $post) {
            try {
                dispatch(new UserPostJob($post));
            } catch (\Exception $e) {
                // Log the error with detailed information
                \Illuminate\Support\Facades\Log::error('Scheduled post failed in command', [
                    'post_id' => $post->id,
                    'platform' => $post->platform,
                    'user_id' => $post->user_id,
                    'repeat_period' => $repeat_period,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Update post status to indicate failure (optional - you may want to add a failed_at column)
                // $post->update(['failed_at' => now()]);
                
                continue;
            }
            $post->update(['command_running' => true]);
        }
    }
}
