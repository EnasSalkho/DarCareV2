<?php

namespace App\Modules\Chat\Models;

use App\Enums\ConversationStatusEnum;
use App\Enums\ConversationTypeEnum;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Conversation extends Model
{
    protected $fillable = [
        'type',
        'service_request_id',
        'status',
        'created_by_type',
        'created_by_id',
        'last_message_id',
        'last_message_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationTypeEnum::class,
            'status' => ConversationStatusEnum::class,
            'last_message_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function creator(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    public function allowsMessaging(): bool
    {
        return $this->status === ConversationStatusEnum::Open;
    }

    public function isSupport(): bool
    {
        return in_array($this->type, [
            ConversationTypeEnum::SupportCustomer,
            ConversationTypeEnum::SupportProvider,
        ], true);
    }
}
