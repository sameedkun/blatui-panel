<?php

namespace App\Models;

use App\Enum\NotificationPushStatus;
use App\Enum\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'link',
        'push_status',
        'push_sent_at',
        'push_error',
        'onesignal_notification_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'push_status' => NotificationPushStatus::class,
            'push_sent_at' => 'datetime',
        ];
    }

    public function isSent(): bool
    {
        return $this->push_status === NotificationPushStatus::Sent;
    }

    public function isFailed(): bool
    {
        return $this->push_status === NotificationPushStatus::Failed;
    }
}
