<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only view of what visitors have been asking the assistant. Useful both
 * for spotting questions it answered badly and for finding sales leads.
 */
class ChatController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('q')->trim()->value();

        $conversations = ChatConversation::query()
            ->with('user')
            ->withCount('messages')
            ->when($search, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('messages', fn ($m) => $m->where('content', 'like', "%{$search}%"))
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))))
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.chat.index', [
            'conversations' => $conversations,
            'search' => $search,
            'stats' => [
                'conversations' => ChatConversation::count(),
                'messages' => ChatConversation::sum('message_count'),
                'today' => ChatConversation::whereDate('last_message_at', today())->count(),
                'tokens' => ChatConversation::sum('input_tokens') + ChatConversation::sum('output_tokens'),
            ],
        ]);
    }

    public function show(Request $request, ChatConversation $conversation)
    {
        $conversation->load('user', 'agent');

        // Opening the transcript is what clears its unread badge.
        $conversation->forceFill(['admin_read_at' => now()])->save();

        $messages = $conversation->messages()->orderBy('id')->get();

        // Polled by the transcript page so a reply from the customer appears
        // without a refresh, the mirror of what the widget does.
        if ($request->wantsJson()) {
            return response()->json([
                'human_handling' => $conversation->human_handling,
                'messages' => $messages->map->only(['id', 'role', 'content']),
            ]);
        }

        return view('admin.chat.show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    /** Sends a reply written by a person, as this store's support team. */
    public function reply(Request $request, ChatConversation $conversation)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $conversation->messages()->create([
            'role' => 'agent',
            'content' => $data['message'],
        ]);

        // Replying is itself a takeover — nobody wants the bot answering over
        // the top of them mid-conversation.
        $conversation->forceFill([
            'human_handling' => true,
            'handled_by' => Auth::id(),
            'needs_human' => false,
            'last_message_at' => now(),
            'admin_read_at' => now(),
            'message_count' => $conversation->messages()->count(),
        ])->save();

        return back()->with('status', 'Reply sent.');
    }

    /** Switches a conversation between the assistant and a person. */
    public function handling(Request $request, ChatConversation $conversation)
    {
        $request->validate(['human_handling' => ['required', 'boolean']]);

        $human = $request->boolean('human_handling');

        $conversation->forceFill([
            'human_handling' => $human,
            'handled_by' => $human ? Auth::id() : null,
            'needs_human' => false,
        ])->save();

        return back()->with('status', $human
            ? "You're handling this conversation — the assistant won't reply until you hand it back."
            : 'Handed back to the assistant.');
    }

    public function destroy(ChatConversation $conversation)
    {
        $conversation->delete();

        return redirect()->route('admin.chat.index')->with('status', 'Conversation deleted.');
    }
}
