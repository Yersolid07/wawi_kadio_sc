<?php

namespace App\Http\Middleware;

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
    public function version(Request $request): ?string
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
        $cmsSettings = \Illuminate\Support\Facades\Cache::remember('cms_settings', 60 * 24, function () {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                return \App\Models\Setting::pluck('value', 'key')->toArray();
            }
            return [];
        });

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    ...$request->user()->only('id', 'name', 'email', 'phone', 'address', 'avatar'),
                    'avatar_url' => $request->user()->avatar_url,
                    'roles' => $request->user()->roles->map->only('name'),
                    'permissions' => $request->user()->getAllPermissions()->map->only('name'),
                    'unreadNotifications' => $request->user()->unreadNotifications()->take(5)->get(),
                    'unreadCount' => $request->user()->unreadNotifications()->count(),
                ] : null,
            ],
            'roles' => $request->user() ? $request->user()->getRoleNames() : [],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
            ],
            'cms_settings' => $cmsSettings,
        ];
    }
}
