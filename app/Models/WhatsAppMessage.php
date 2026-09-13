<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Database\Factories\WhatsAppMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A record of every WhatsApp message the shop sent, successful or not.
 *
 * Kept so a shop can answer "did the customer get their bill?" and so a
 * redemption that went through without a code can be explained afterwards.
 */
#[Fillable([
    'to', 'purpose', 'template', 'status', 'provider_message_id', 'error', 'sent_at', 'sent_by',
])]
class WhatsAppMessage extends Model
{
    use BelongsToStore;

    /** @use HasFactory<WhatsAppMessageFactory> */
    use HasFactory;

    protected $table = 'whatsapp_messages';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * The bill or the member this was about.
     *
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function wasSent(): bool
    {
        return $this->status === 'sent';
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
