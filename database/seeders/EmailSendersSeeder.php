<?php

namespace Database\Seeders;

use App\Enum\MailPurpose;
use App\Models\EmailSender;
use Illuminate\Database\Seeder;

/**
 * Idempotent and safe to re-run in production: ensures one {@see EmailSender}
 * row exists per {@see MailPurpose} case, never touching rows an admin has
 * already configured.
 */
class EmailSendersSeeder extends Seeder
{
    public function run(): void
    {
        foreach (MailPurpose::cases() as $purpose) {
            EmailSender::query()->firstOrCreate(
                ['key' => $purpose->value],
                [
                    'label' => $purpose->label(),
                    'local_part' => 'noreply',
                    'from_name' => config('app.name'),
                    'is_enabled' => true,
                ],
            );
        }
    }
}
