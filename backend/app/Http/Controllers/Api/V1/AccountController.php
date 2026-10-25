<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Requests\Account\VerifyEmailRequest;
use App\Http\Resources\UserResource;
use App\Mail\AccountDeletedMail;
use App\Mail\EmailChangedMail;
use App\Mail\PasswordChangedMail;
use App\Models\AuthCode;
use App\Models\KitchenDish;
use App\Models\User;
use App\Services\AuthCodeService;
use App\Support\Activity;
use App\Support\Mailer;
use App\Support\Tokens;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Group('Account', weight: 3)]
class AccountController extends Controller
{
    public function __construct(private readonly AuthCodeService $codes) {}

    /**
     * Me
     *
     * The signed-in user with kitchen settings, location, reminder and counts.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Update profile
     *
     * Changing the email needs `current_password`; a 6-digit code is emailed to the new
     * address and the change applies after `POST /me/email/verify`.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $pending = false;

        if (isset($data['email']) && $data['email'] !== $user->email) {
            if (blank($data['current_password'] ?? null)) {
                throw ValidationException::withMessages(['current_password' => ['Enter your password to change email.']]);
            }
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['That password is incorrect.']]);
            }

            $wait = $this->codes->resendWait(AuthCode::where('purpose', AuthCode::EMAIL_CHANGE)->where('user_id', $user->id)->whereNull('consumed_at')->latest('id')->first());
            if ($wait > 0) {
                return response()->json(['message' => "Wait {$wait}s before asking for another code.", 'retry_after' => $wait], 429);
            }

            $this->codes->send(AuthCode::EMAIL_CHANGE, $data['email'], $user, $user->firstName());
            $pending = true;
            Activity::log($user, 'account.email_change_requested', 'Asked to change email', null, ['new_email' => $data['email']]);
        }

        if (isset($data['name']) && trim($data['name']) !== $user->name) {
            $user->update(['name' => trim($data['name'])]);
            Activity::log($user, 'account.profile_updated', 'Changed their name');
        }

        return response()->json([
            'data' => (new UserResource($user->fresh()))->resolve($request),
            'meta' => ['email_change_pending' => $pending],
        ]);
    }

    /**
     * Confirm new email
     *
     * The 6-digit code sent to the new address.
     */
    public function verifyEmail(VerifyEmailRequest $request): UserResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $code = AuthCode::where('purpose', AuthCode::EMAIL_CHANGE)->where('user_id', $user->id)->whereNull('consumed_at')->latest('id')->first();

        if (! $code) {
            return response()->json(['message' => 'No email change is waiting. Change your email again.'], 422);
        }

        $result = $this->codes->verify($code, $data['code']);

        if ($result['status'] !== AuthCodeService::OK) {
            return response()->json([
                'message' => $result['status'] === AuthCodeService::WRONG ? 'That code doesn’t match.' : 'Request a new code to try again.',
                'errors' => ['code' => ['That code doesn’t match.']],
                'attempts_left' => $result['attempts_left'] ?? 0,
            ], $result['status'] === AuthCodeService::LOCKED ? 429 : 422);
        }

        if (User::where('email', $code->email)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages(['email' => ['This email already has an account.']]);
        }

        $old = $user->email;
        $user->forceFill(['email' => $code->email, 'email_verified_at' => now()])->save();
        $code->forceFill(['consumed_at' => now()])->save();
        Activity::log($user, 'account.email_changed', 'Changed email', null, ['from' => $old, 'to' => $code->email]);
        Mailer::notice($old, new EmailChangedMail($old, $code->email, Mailer::when($user)));

        return new UserResource($user->fresh());
    }

    /**
     * Change password
     */
    public function password(ChangePasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        }

        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();

        $signOut = $request->boolean('sign_out_other_devices', true);
        if ($signOut) {
            $current = Tokens::currentId($user);
            $user->tokens()->where('id', '!=', $current)->delete();
            $user->devices()->where(fn ($q) => $q->whereNull('personal_access_token_id')->orWhere('personal_access_token_id', '!=', $current))->delete();
        }

        Activity::log($user, 'account.password_changed', 'Changed password', null, ['signed_out_other_devices' => $signOut]);
        Mailer::notice($user->email, new PasswordChangedMail($user, Mailer::when($user), Mailer::device($request), $signOut));

        return response()->noContent();
    }

    /**
     * Delete account
     *
     * Needs the password and the word DELETE. Removes the user and all their data.
     */
    #[BodyParameter('password', 'Current password', required: true, type: 'string', example: 'kitchen123')]
    #[BodyParameter('confirmation', 'Must be exactly DELETE', required: true, type: 'string', example: 'DELETE')]
    public function destroy(DeleteAccountRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['That password is incorrect.']]);
        }

        // Keep no personal data about a deleted account: a masked email and the old id are enough for support.
        Activity::log(null, 'account.deleted', 'Deleted account '.$user->maskedEmail(), null, ['user_id' => $user->id]);

        $goodbye = new AccountDeletedMail(
            $user->email,
            $user->kitchenDishes()->count(),
            $user->mealLogs()->count(),
            Media::query()->where('model_type', (new KitchenDish)->getMorphClass())->whereIn('model_id', $user->kitchenDishes()->select('id'))->count(),
        );

        $user->delete(); // also removes tokens and dish photos (User::booted)

        Mailer::notice($goodbye->email, $goodbye);

        return response()->noContent();
    }
}
