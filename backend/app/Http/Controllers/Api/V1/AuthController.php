<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Device;
use App\Models\User;
use App\Services\KitchenService;
use App\Services\NotificationService;
use App\Mail\WelcomeMail;
use App\Support\Activity;
use App\Support\Mailer;
use App\Support\Rules;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 */
#[Group('Auth', weight: 1)]
class AuthController extends Controller
{
    /**
     * Sign up
     *
     * Creates the account together with everything chosen during onboarding:
     * family & budget, preferences, location, at least 10 dishes and any custom dishes.
     *
     * @unauthenticated
     */
    public function register(Request $request, KitchenService $kitchens, NotificationService $notifications): JsonResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : $request->input('email')]);

        $data = $request->validate([
            'name' => Rules::name(),
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => [...Rules::password(), 'confirmed'],
            'password_confirmation' => ['required'],
            'terms_accepted' => ['accepted'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'timezone' => ['nullable', 'timezone:all'],
            'kitchen' => ['required', 'array'],
            ...Rules::kitchen('kitchen.'),
            'location' => ['nullable', 'array'],
            'location.mode' => ['required_with:location', Rule::in(['gps', 'city'])],
            'location.city_id' => ['required_if:location.mode,city', 'nullable', 'integer', 'exists:cities,id'],
            'location.lat' => ['required_if:location.mode,gps', 'nullable', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_if:location.mode,gps', 'nullable', 'numeric', 'between:-180,180'],
            'dish_ids' => ['present', 'array'],
            'dish_ids.*' => ['integer', 'distinct', 'exists:dishes,id'],
            'custom_dishes' => ['nullable', 'array', 'max:50'],
            'custom_dishes.*.name' => ['required', 'string', 'min:2', 'max:40', 'regex:'.Rules::DISH_NAME_REGEX],
            'custom_dishes.*.category' => ['nullable', 'string', 'exists:categories,key'],
        ], [
            ...Rules::nameMessages(),
            ...Rules::emailMessages(),
            ...Rules::passwordMessages(),
            ...Rules::kitchenMessages('kitchen.'),
            'password_confirmation.required' => 'Confirm your password.',
            'terms_accepted.accepted' => 'Please accept the terms to continue.',
        ]);

        $total = count($data['dish_ids']) + count($data['custom_dishes'] ?? []);
        $min = (int) config('handi.picks.min_onboarding_dishes', 10);
        if ($total < $min) {
            throw ValidationException::withMessages(['dish_ids' => ["Pick at least {$min} dishes."]]);
        }

        $user = DB::transaction(function () use ($data, $kitchens) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'password' => $data['password'],
                'timezone' => $data['timezone'] ?? config('handi.timezone'),
                'terms_accepted_at' => now(),
                'password_changed_at' => now(),
            ]);

            $kitchen = $user->kitchen()->create([
                'family_size' => $data['kitchen']['family_size'],
                'budget' => $data['kitchen']['budget'],
                'repeat_gap_days' => $data['kitchen']['repeat_gap_days'],
                'meals' => array_values($data['kitchen']['meals']),
            ]);

            if (! empty($data['location'])) {
                $kitchens->applyLocation($kitchen, $data['location']);
            }

            $kitchens->addLibraryDishes($user, $data['dish_ids']);

            foreach ($data['custom_dishes'] ?? [] as $custom) {
                if (! $kitchens->nameTaken($user, $custom['name'])) {
                    $kitchens->createCustomDish($user, $custom);
                }
            }

            return $user;
        });

        $count = $user->kitchenDishes()->count();
        $notifications->notify($user, NotificationType::KitchenReady, 'Your kitchen is ready',
            "{$count} dishes saved. Your first morning picks arrive tomorrow at ".self::time12($user->kitchen->notify_time).'.',
            'View dishes', '/dishes', 'biryani');

        Activity::log($user, 'auth.registered', 'Created an account', $user, ['dishes' => $count]);
        Mailer::notice($user->email, new WelcomeMail($user));

        return $this->tokenResponse($user, $data['device_name'] ?? null, 201);
    }

    /**
     * Log in
     *
     * Five wrong passwords lock this email (from this IP) for 60 seconds.
     * Wrong password → 401 with `attempts_left`; locked → 429 with `retry_after`.
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $request->merge(['email' => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : $request->input('email')]);

        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'email.required' => 'Enter your email.',
            'email.email' => 'That doesn’t look like an email.',
            'password.required' => 'Enter your password.',
        ]);

        $key = 'login:'.$data['email'].'|'.$request->ip();
        $max = (int) config('handi.auth.max_login_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many attempts. For your safety, try again in {$seconds}s, or reset your password.",
                'retry_after' => $seconds,
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, (int) config('handi.auth.lockout_seconds', 60));
            $left = max(0, $max - RateLimiter::attempts($key));

            if ($user) {
                Activity::log($user, 'auth.login_failed', 'Wrong password', null, ['attempts_left' => $left]);
            }

            if ($left === 0) {
                $seconds = RateLimiter::availableIn($key);

                return response()->json([
                    'message' => "Too many attempts. For your safety, try again in {$seconds}s, or reset your password.",
                    'retry_after' => $seconds,
                ], 429);
            }

            return response()->json([
                'message' => 'Email or password is incorrect. '.$left.($left === 1 ? ' try' : ' tries').' left.',
                'attempts_left' => $left,
            ], 401);
        }

        RateLimiter::clear($key);
        $user->forceFill(['last_login_at' => now(), 'last_active_at' => now()])->save();
        Activity::log($user, 'auth.login', 'Logged in', null, ['device' => $data['device_name'] ?? null]);

        return $this->tokenResponse($user, $data['device_name'] ?? null);
    }

    /**
     * Log out
     *
     * Revokes this phone's token and stops reminders on it.
     */
    public function logout(Request $request): Response
    {
        $tokenId = \App\Support\Tokens::currentId($request->user());

        if ($tokenId) {
            Device::where('personal_access_token_id', $tokenId)->delete();
            $request->user()->tokens()->whereKey($tokenId)->delete();
        }

        Activity::log($request->user(), 'auth.logout', 'Logged out');

        return response()->noContent();
    }

    private function tokenResponse(User $user, ?string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'mobile')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user->fresh()),
            ],
        ], $status);
    }

    /** "09:00" → "9:00 AM" */
    public static function time12(string $time): string
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return (($h % 12) ?: 12).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).' '.($h < 12 ? 'AM' : 'PM');
    }
}
