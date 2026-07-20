<?php

namespace Database\Seeders;

use App\Models\Policy;
use App\Models\PolicyVersion;
use Illuminate\Database\Seeder;

class PoliciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $policies = [
            [
                'key' => 'privacy',
                'title' => 'Privacy Policy',
                'content' => 'This is the default Privacy Policy content. Please edit it in the admin panel.',
                'version' => '1.0',
            ],
            [
                'key' => 'terms',
                'title' => 'Terms of Service',
                'content' => 'This is the default Terms of Service content. Please edit it in the admin panel.',
                'version' => '1.0',
            ],
        ];

        foreach ($policies as $data) {
            $policy = Policy::firstOrCreate(
                ['key' => $data['key']],
                ['title' => $data['title']]
            );

            // Create initial active version if none exists
            if (! $policy->versions()->where('is_active', true)->exists()) {
                PolicyVersion::create([
                    'policy_id' => $policy->id,
                    'version' => $data['version'],
                    'content' => $data['content'],
                    'is_active' => true,
                    'published_at' => now(),
                ]);
            }
        }
    }
}
