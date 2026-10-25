<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeMail;
use App\Models\Device;
use App\Models\User;
use App\Services\KitchenService;
use App\Services\NotificationService;
use App\Support\Activity;
use App\Support\Mailer;
use App\Support\Tokens;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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
    public function register(RegisterRequest $request, KitchenService $kitchens, NotificationService $notifications): JsonResponse
    {
        $data = $request->validated();

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
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $key = 'login:'.$data['email'].'|'.$request->ip();
        $max = (int) config('handi.auth.max_login_attempts', 5);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Too many attempts. For your safety, try again in {$seconds}s, or reset your password.",
                'retry_after' => $seconds,
            ], 429);
        }

        // Second limit per email across all IPs, so one account can't be brute-forced from many addresses.
        $emailKey = 'login-email:'.$data['email'];
        if (RateLimiter::tooManyAttempts($emailKey, 20)) {
            $seconds = RateLimiter::availableIn($emailKey);

            return response()->json([
                'message' => "Too many attempts. For your safety, try again in {$seconds}s, or reset your password.",
                'retry_after' => $seconds,
            ], 429);
        }

        $user = User::where('email', $data['email'])->first();
        // Same work whether or not the email exists, so response time doesn't reveal registered emails.
        $passwordOk = Hash::check($data['password'], $user?->password ?? '$2y$12$'.str_repeat('a', 53));

        if (! $user || ! $passwordOk) {
            RateLimiter::hit($key, (int) config('handi.auth.lockout_seconds', 60));
            RateLimiter::hit($emailKey, 3600);
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
        $tokenId = Tokens::currentId($request->user());

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
