<?php

namespace App\Models;

use App\Enum\AnnouncementPushStatus;
use App\Enum\AnnouncementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
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
            'type' => AnnouncementType::class,
            'push_status' => AnnouncementPushStatus::class,
            'push_sent_at' => 'datetime',
        ];
    }

    public function isSent(): bool
    {
        return $this->push_status === AnnouncementPushStatus::Sent;
    }

    public function isFailed(): bool
    {
        return $this->push_status === AnnouncementPushStatus::Failed;
    }
}
