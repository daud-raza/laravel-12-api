<?php

namespace Modules\Chat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Chat\Database\Factories\ConversationFactory;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return ConversationFactory::new();
    }

    protected $fillable = [
        'type',
        'created_by',
        'direct_hash',
        'last_message_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    /**
     * Deterministic hash for a direct conversation between two users.
     * Order-independent so (a,b) and (b,a) map to the same conversation.
     */
    public static function directHash(int $userA, int $userB): string
    {
        $pair = [$userA, $userB];
        sort($pair);

        return hash('sha256', $pair[0].'-'.$pair[1]);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['last_read_at', 'last_read_message_id', 'joined_at', 'muted_at', 'role'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
