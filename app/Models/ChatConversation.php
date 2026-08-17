<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'visitor_token', 'title', 'needs_human', 'human_handling', 'handled_by'])]
class ChatConversation extends Model
{
    protected function casts(): array
    {
        return [
            'needs_human' => 'boolean',
            'human_handling' => 'boolean',
            'last_message_at' => 'datetime',
            'admin_read_at' => 'datetime',
        ];
    }

    /** Conversations with a customer message the owner hasn't seen yet. */
    public function scopeUnread($query)
    {
        return $query->whereNotNull('last_message_at')
            ->where(fn ($q) => $q->whereNull('admin_read_at')->orWhereColumn('last_message_at', '>', 'admin_read_at'));
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /** First thing the visitor asked, used as the transcript title in admin. */
    public function titleOrExcerpt(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        $first = $this->messages()->where('role', 'user')->orderBy('id')->value('content');

        return $first ? Str::limit($first, 60) : 'New conversation';
    }
}
