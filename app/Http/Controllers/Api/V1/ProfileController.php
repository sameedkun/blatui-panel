<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\V1\User\UpdatePasswordRequest;
use App\Http\Requests\V1\User\UpdateProfileRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        return $this->success(['user' => new UserResource($request->user())]);
    }

    /**
     * Mirrors Livewire\Admin\Account\Index::saveProfile()'s avatar handling
     * (default disk, auto-generated filename, old file deleted, path — never
     * a URL — stored on the `avatar` column) and its audit shape (avatar is
     * excluded from the generic diff and logged as a boolean flag instead,
     * same as password changes never log the raw value).
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $before = $user->getOriginal();

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->validated('name');
        }

        $avatarChanged = false;

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::delete($user->avatar);
            }

            $data['avatar'] = $request->file('avatar')->store('avatars');
            $avatarChanged = true;
        }

        if ($data !== []) {
            $user->update($data);
        }

        $changes = ActivityLogger::diff($user, $before, extraExcept: ['avatar']);

        if ($changes !== [] || $avatarChanged) {
            ActivityLogger::log(ActivityModule::User, ActivityAction::Updated, $user, [
                ...$changes,
                ...($avatarChanged ? ['avatar_changed' => true] : []),
            ], causer: $user);
        }

        return $this->success(['user' => new UserResource($user)], 'Profile updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'password_changed_at' => now(),
        ])->save();

        ActivityLogger::log(ActivityModule::User, ActivityAction::Updated, $user, [
            'password_changed' => true,
        ], causer: $user);

        return $this->success(null, 'Password updated successfully.');
    }
}
