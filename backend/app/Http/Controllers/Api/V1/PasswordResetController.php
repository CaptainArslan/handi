<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetEmailRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
use App\Mail\PasswordChangedMail;
use App\Models\AuthCode;
use App\Models\User;
use App\Services\AuthCodeService;
use App\Support\Activity;
use App\Support\Mailer;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
    public function forgot(ResetEmailRequest $request): JsonResponse
    {
        $email = $request->validated('email');
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
    public function resend(ResetEmailRequest $request): JsonResponse
    {
        $email = $request->validated('email');
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
    public function verify(VerifyResetCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $email = $data['email'];

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
    public function reset(ResetPasswordRequest $request): Response|JsonResponse
    {
        $data = $request->validated();

        $code = $this->codes->findByResetToken($data['reset_token']);

        if (! $code || ! $code->user) {
            return response()->json(['message' => 'This reset link has expired. Request a new code.', 'errors' => ['reset_token' => ['This reset link has expired. Request a new code.']]], 422);
        }

        // Use the token exactly once, atomically: a second parallel request gets "expired".
        if (AuthCode::whereKey($code->id)->whereNull('consumed_at')->update(['consumed_at' => now()]) === 0) {
            return response()->json(['message' => 'This reset link has expired. Request a new code.', 'errors' => ['reset_token' => ['This reset link has expired. Request a new code.']]], 422);
        }

        $user = $code->user;
        DB::transaction(function () use ($user, $data) {
            $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();
            $user->tokens()->delete();
            $user->devices()->delete();
        });

        Activity::log($user, 'auth.password_reset', 'Reset the password with an email code');
        Mailer::notice($user->email, new PasswordChangedMail($user, Mailer::when($user), Mailer::device($request), true));

        return response()->noContent();
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
