<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuthCode;
use App\Models\User;
use App\Services\AuthCodeService;
use App\Support\Activity;
use App\Support\Rules;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Password reset by a 6-digit email code.
 * For privacy the API never confirms whether an email is registered.
 */
#[Group('Auth · password reset', weight: 2)]
class PasswordResetController extends Controller
{
    public function __construct(private readonly AuthCodeService $codes) {}

    /**
     * Send code
     *
     * Always 202. Includes the masked email, how long the code is valid and when it can be resent.
     *
     * @unauthenticated
     */
    #[BodyParameter('email', 'The account email', required: true, type: 'string', format: 'email', example: 'hina@example.com')]
    public function forgot(Request $request): JsonResponse
    {
        $email = $this->email($request);
        $user = User::where('email', $email)->first();
        $current = $this->codes->current(AuthCode::PASSWORD_RESET, $email);

        if ($user && $this->codes->resendWait($current) === 0) {
            $current = $this->codes->send(AuthCode::PASSWORD_RESET, $email, $user, $user->firstName());
            Activity::log($user, 'auth.reset_requested', 'Asked for a password reset code');
        }

        return $this->accepted($email, $current);
    }

    /**
     * Resend code
     *
     * 429 with `retry_after` if asked again within 45 seconds.
     *
     * @unauthenticated
     */
    #[BodyParameter('email', 'The account email', required: true, type: 'string', format: 'email', example: 'hina@example.com')]
    public function resend(Request $request): JsonResponse
    {
        $email = $this->email($request);
        $current = $this->codes->current(AuthCode::PASSWORD_RESET, $email);
        $wait = $this->codes->resendWait($current);

        if ($wait > 0) {
            return response()->json(['message' => "You can resend the code in {$wait}s.", 'retry_after' => $wait], 429);
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $current = $this->codes->send(AuthCode::PASSWORD_RESET, $email, $user, $user->firstName());
        }

        return $this->accepted($email, $current);
    }

    /**
     * Verify code
     *
     * Returns a `reset_token` for “Set new password”. Wrong code → 422 with `attempts_left`;
     * after 5 wrong codes → 429 (request a new code).
     *
     * @unauthenticated
     */
    #[BodyParameter('email', 'The account email', required: true, type: 'string', format: 'email', example: 'hina@example.com')]
    public function verify(Request $request): JsonResponse
    {
        $email = $this->email($request);
        $data = $request->validate(['code' => ['required', 'digits:6']], [
            'code.required' => 'Enter the 6-digit code.',
            'code.digits' => 'Enter the 6-digit code.',
        ]);

        $code = $this->codes->current(AuthCode::PASSWORD_RESET, $email);

        if (! $code) {
            return response()->json(['message' => 'That code doesn’t match.', 'errors' => ['code' => ['That code doesn’t match.']], 'attempts_left' => 4], 422);
        }

        $result = $this->codes->verify($code, $data['code']);

        return match ($result['status']) {
            AuthCodeService::OK => response()->json(['data' => ['reset_token' => $result['reset_token']]]),
            AuthCodeService::LOCKED => response()->json(['message' => 'Too many attempts. Request a new code to try again.', 'attempts_left' => 0], 429),
            AuthCodeService::EXPIRED => response()->json(['message' => 'This code has expired. Request a new one.', 'errors' => ['code' => ['This code has expired. Request a new one.']]], 422),
            default => response()->json([
                'message' => 'That code doesn’t match. '.$result['attempts_left'].($result['attempts_left'] === 1 ? ' attempt' : ' attempts').' left.',
                'errors' => ['code' => ['That code doesn’t match.']],
                'attempts_left' => $result['attempts_left'],
            ], 422),
        };
    }

    /**
     * Set new password
     *
     * Logs the account out on every phone.
     *
     * @unauthenticated
     */
    public function reset(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'reset_token' => ['required', 'string'],
            'password' => [...Rules::password(), 'regex:/^\S(.*\S)?$/s', 'confirmed'],
            'password_confirmation' => ['required'],
        ], [
            ...Rules::passwordMessages(),
            'password.regex' => 'Use 8+ characters with a letter and a number, and no spaces at the start or end.',
            'password_confirmation.required' => 'Confirm your new password.',
        ]);

        $code = $this->codes->findByResetToken($data['reset_token']);

        if (! $code || ! $code->user) {
            return response()->json(['message' => 'This reset link has expired. Request a new code.', 'errors' => ['reset_token' => ['This reset link has expired. Request a new code.']]], 422);
        }

        $user = $code->user;
        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();
        $user->tokens()->delete();
        $user->devices()->delete();
        $code->forceFill(['consumed_at' => now()])->save();

        Activity::log($user, 'auth.password_reset', 'Reset the password with an email code');

        return response()->noContent();
    }

    private function email(Request $request): string
    {
        $request->merge(['email' => is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : $request->input('email')]);

        return $request->validate(['email' => ['required', 'string', 'email']], [
            'email.required' => 'Enter the email you signed up with.',
            'email.email' => 'Enter a valid email, like name@example.com.',
        ])['email'];
    }

    private function accepted(string $email, ?AuthCode $code): JsonResponse
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return response()->json(['data' => [
            'masked_email' => mb_substr($local, 0, 1).'***@'.$domain,
            'expires_in' => (int) config('handi.auth.code_ttl_minutes', 15) * 60,
            'resend_in' => max($this->codes->resendWait($code), $code ? 0 : (int) config('handi.auth.code_resend_seconds', 45)),
        ]], 202);
    }
}
