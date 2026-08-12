<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use Illuminate\Http\Request;

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

    public function show(ChatConversation $conversation)
    {
        $conversation->load('user');

        return view('admin.chat.show', [
            'conversation' => $conversation,
            'messages' => $conversation->messages()->orderBy('id')->get(),
        ]);
    }

    public function destroy(ChatConversation $conversation)
    {
        $conversation->delete();

        return redirect()->route('admin.chat.index')->with('status', 'Conversation deleted.');
    }
}
