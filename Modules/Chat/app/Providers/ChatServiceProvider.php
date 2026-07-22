<?php

namespace Modules\Chat\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Chat\Policies\ConversationPolicy;
use Modules\Chat\Policies\MessagePolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ChatServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Chat';

    public function boot(): void
    {
        parent::boot();

        // Policies
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);

        // Rate limiter for message sending
        RateLimiter::for('chat-send', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'You are sending messages too quickly. Please slow down.',
                    ], 429);
                });
        });

        // Broadcast channel — only participants may listen.
        Broadcast::channel('conversation.{conversation}', function ($user, Conversation $conversation) {
            return $conversation->participants()->whereKey($user->id)->exists();
        });
    }

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'chat';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
