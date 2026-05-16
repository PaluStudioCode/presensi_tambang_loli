<?php

namespace App\Http\Middleware;

use App\Support\PublicFileUrl;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'id_number' => $user->id_number,
                    'full_name' => $user->full_name,
                    'department' => $user->department,
                    'address' => $user->address,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_photo' => $user->profile_photo,
                    'profile_photo_url' => PublicFileUrl::make($user->profile_photo),
                    'created_at' => $user->created_at,
                    'email_verified_at' => $user->email_verified_at ?? null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
