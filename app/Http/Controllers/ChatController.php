<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Services\Chat\ChatAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Backend for the floating live-chat widget. Guests can chat as well as
 * customers, so conversations are keyed by a token kept in the session.
 */
class ChatController extends Controller
{
    protected const SESSION_KEY = 'chat.visitor_token';

    public function __construct(protected ChatAssistant $assistant) {}

    /**
     * Existing transcript, so the conversation survives a page reload.
     *
     * Also polled while the widget is open, which is how a reply typed by a
     * human in the admin panel reaches the customer. `after` keeps that cheap:
     * only messages newer than the last one the browser has.
     */
    public function history(Request $request): JsonResponse
    {
        $conversation = $this->conversation($request, create: false);
        $after = (int) $request->query('after', 0);

        $messages = $conversation
            ? $conversation->messages()
                ->whereNull('error')
                ->when($after > 0, fn ($q) => $q->where('id', '>', $after))
                ->orderBy('id')
                ->get(['id', 'role', 'content'])
            : collect();

        return response()->json([
            'enabled' => $this->assistant->isEnabled() || (bool) $conversation?->human_handling,
            'greeting' => (string) config('assistant.greeting'),
            'human' => (bool) $conversation?->human_handling,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $this->conversation($request, create: false);
        $human = (bool) $conversation?->human_handling;

        // A person is answering this one, so the message just needs storing.
        if (! $human && ! $this->assistant->isEnabled()) {
            return response()->json([
                'error' => 'Live chat is not available right now. Please email us instead.',
            ], 503);
        }

        // Each reply costs real money at Anthropic, so cap what one visitor can
        // spend before a human has to get involved.
        $limiterKey = 'chat:'.($request->user()?->id ?? $request->ip());
        $perHour = max(1, (int) config('assistant.rate_limit_per_hour', 25));

        if (RateLimiter::tooManyAttempts($limiterKey, $perHour)) {
            return response()->json([
                'error' => 'You\'ve reached the chat limit for this hour. Please email our support team and we\'ll pick it up from there.',
            ], 429);
        }

        RateLimiter::hit($limiterKey, 3600);

        $conversation = $this->conversation($request, create: true);

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'message_count' => $conversation->messages()->count(),
        ])->save();

        if ($human) {
            return response()->json([
                'human' => true,
                'id' => $userMessage->id,
            ]);
        }

        try {
            $reply = $this->assistant->reply($conversation, $userMessage);
        } catch (RuntimeException $e) {
            // The customer is stuck, so flag it for a person to pick up.
            $conversation->forceFill(['needs_human' => true])->save();

            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'reply' => $reply->content,
            'id' => $reply->id,
        ]);
    }

    /** Clears the transcript and starts a fresh conversation. */
    public function reset(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    protected function conversation(Request $request, bool $create): ?ChatConversation
    {
        $token = $request->session()->get(self::SESSION_KEY);

        if (! $token) {
            if (! $create) {
                return null;
            }

            $token = (string) Str::uuid();
            $request->session()->put(self::SESSION_KEY, $token);
        }

        $query = ChatConversation::where('visitor_token', $token);

        if (! $create) {
            return $query->first();
        }

        $conversation = $query->first() ?? new ChatConversation(['visitor_token' => $token]);

        // A visitor who logs in mid-conversation should keep their transcript,
        // and it should now be attributed to their account.
        if (Auth::check() && $conversation->user_id !== Auth::id()) {
            $conversation->user_id = Auth::id();
        }

        $conversation->save();

        return $conversation;
    }
}
