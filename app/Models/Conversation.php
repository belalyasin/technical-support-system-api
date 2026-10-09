<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'user_id', 'last_message',
    ];

    protected $casts = [
        'last_message' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            $conversation->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * Conversations are addressed by their uuid in URLs, never by the numeric id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * The customer the conversation belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Everyone taking part: the customer and the support staff who joined.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('read_at')
            ->withTimestamps();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists();
    }

    /**
     * Record that the user has read everything up to now. Adds them to the conversation if needed.
     */
    public function markAsReadBy(User $user): void
    {
        $this->users()->syncWithoutDetaching([$user->getKey() => ['read_at' => now()]]);
    }

    /**
     * Conversations the user may see: all of them for staff, otherwise only those they take part in.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isStaff()) {
            return;
        }

        $query->whereHas('users', fn (Builder $users) => $users->whereKey($user->getKey()));
    }

    /**
     * Adds an unread_count column: messages from other people sent after the user last read the conversation.
     */
    public function scopeWithUnreadCountFor(Builder $query, User $user): void
    {
        $lastRead = fn ($pivot) => $pivot->from('conversation_user')
            ->whereColumn('conversation_user.conversation_id', 'conversations.id')
            ->where('conversation_user.user_id', $user->getKey())
            ->whereNotNull('conversation_user.read_at');

        $query->addSelect(['unread_count' => Message::query()
            ->selectRaw('count(*)')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->where('messages.user_id', '!=', $user->getKey())
            ->where(function ($unread) use ($lastRead) {
                $unread
                    ->whereNotExists(fn ($pivot) => $lastRead($pivot)->selectRaw('1'))
                    ->orWhere('messages.created_at', '>', fn ($pivot) => $lastRead($pivot)->select('conversation_user.read_at')->limit(1));
            }),
        ]);
    }
}
