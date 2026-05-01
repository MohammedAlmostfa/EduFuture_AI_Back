<?php

namespace App\Services\Auth;

use Exception;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * Create a new profile for the authenticated user.
     *
     * @param array $data Profile data (name, birthday, phone, address, longitude, latitude, photo)
     * @return array Result with status and message
     */
    public function createProfile(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                $user = Auth::user();

                // Check if the user already has a profile
                if ($user->profile) {
                    return [
                        'status' => 400,
                        'message' => [
                            'errorDetails' => [__('profile.user_already_has_profile')],
                        ],
                    ];
                }

                // Update user's name if provided
                if (isset($data['name'])) {
                    $user->name = $data['name'];
                    $user->save();
                }

                // Create profile
                $profile = Profile::create([
                    'birthday'  => $data['birthday'] ?? null,
                    'phone'     => $data['phone'] ?? null,
                    'address'   => $data['address'] ?? null,
                    'user_id'   => $user->id,
                    'longitude' => $data['longitude'] ?? null,
                    'latitude'  => $data['latitude'] ?? null,
                ]);

                // Upload photo if provided
                if (!empty($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
                    $image = $data['photo'];
                    $imageName = Str::random(32) . '.' . $image->getClientOriginalExtension();
                    $folder = 'users/photos/' . now()->format('Y-m-d');
                    $path = $image->storeAs($folder, $imageName, 'public');

                    $user->photo()->create(['url' => $path]);
                }

                return [
                    'status'  => 200,
                    'message' => __('profile.profile_created_successfully'),
                ];
            });
        } catch (Exception $e) {
            Log::error('Error occurred while creating profile: ' . $e->getMessage());

            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => __('general.failed'),
                ],
            ];
        }
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param array $data Profile data to update
     * @return array Result with status and message
     */
    public function updateProfile(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {
                $user = Auth::user();
                $profile = $user->profile;

                // Check if the user has a profile
                if (!$profile) {
                    return [
                        'status' => 404,
                        'message' => [
                            'errorDetails' => [__('profile.user_does_not_have_profile')],
                        ],
                    ];
                }

                // Update user's name if provided
                if (isset($data['name'])) {
                    $user->name = $data['name'];
                    $user->save();
                }

                // Update profile fields
                $profile->update([
                    'birthday'  => $data['birthday'] ?? $profile->birthday,
                    'phone'     => $data['phone'] ?? $profile->phone,
                    'address'   => $data['address'] ?? $profile->address,
                    'longitude' => $data['longitude'] ?? $profile->longitude,
                    'latitude'  => $data['latitude'] ?? $profile->latitude,
                ]);

                // Upload new photo and delete old one
                if (!empty($data['photo']) && $data['photo'] instanceof \Illuminate\Http\UploadedFile) {
                    $oldPhoto = $user->photo->first(); // Get the first photo only
                    if ($oldPhoto) {
                        // Delete old file from storage
                        if (Storage::disk('public')->exists($oldPhoto->url)) {
                            Storage::disk('public')->delete($oldPhoto->url);
                        }
                        // Delete old record from database
                        $oldPhoto->delete();
                    }

                    // Upload new photo
                    $image = $data['photo'];
                    $imageName = Str::random(32) . '.' . $image->getClientOriginalExtension();
                    $folder = 'users/photos/' . now()->format('Y-m-d');
                    $path = $image->storeAs($folder, $imageName, 'public');

                    $user->photo()->create(['url' => $path]);
                }

                return [
                    'status'  => 200,
                    'message' => __('profile.profile_updated_successfully'),
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error occurred while updating profile: ' . $e->getMessage());

            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => __('general.failed'),
                ],
            ];
        }
    }

    /**
     * Retrieve the authenticated user's data with profile and photo.
     *
     * @return array User data
     */
    public function getMe(): array
    {
        try {
            $user = Auth::user();



            $user = User::select('id', 'name')
                ->with([
                    'photo:id,photoable_id,photoable_type,url',
                    'profile:id,user_id,birthday,phone,address,latitude,longitude',
                    'ratingsReceived' => fn($q) => $q->latest()->limit(5)
                        ->select('id', 'rate', 'review', 'user_id', 'rated_user_id')
                        ->with([
                            'reviewer:id,name',
                            'reviewer.photo:id,photoable_id,photoable_type,url'
                        ]),
                    'items' => fn($q) => $q->latest()->limit(5)
                        ->select('id', 'name', 'price', 'user_id')

                ])
                ->withAvg('ratingsReceived', 'rate')
                ->withCount('ratingsReceived')
                ->find(Auth::id());


            return [
                'status'  => 200,
                'message' => __('profile.user_data_retrieved_successfully'),
                'data'    => $user,
            ];
        } catch (Exception $e) {
            Log::error('Error occurred while retrieving user data: ' . $e->getMessage());

            return [
                'status' => 500,
                'message' => [
                    'errorDetails' => __('general.failed'),
                ],
            ];
        }
    }
}
