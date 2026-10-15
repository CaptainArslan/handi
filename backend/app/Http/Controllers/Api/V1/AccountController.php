<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuthCode;
use App\Models\User;
use App\Services\AuthCodeService;
use App\Mail\AccountDeletedMail;
use App\Mail\EmailChangedMail;
use App\Mail\PasswordChangedMail;
use App\Models\KitchenDish;
use App\Support\Activity;
use App\Support\Mailer;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Support\Rules;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 */
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
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if ($request->has('email') && is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }

        $data = $request->validate([
            'name' => ['sometimes', ...Rules::name()],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'string'],
        ], [...Rules::nameMessages(), ...Rules::emailMessages()]);

        $pending = false;

        if (isset($data['email']) && $data['email'] !== $user->email) {
            if (blank($data['current_password'] ?? null)) {
                throw ValidationException::withMessages(['current_password' => ['Enter your password to change email.']]);
            }
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => ['That password is incorrect.']]);
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
    public function verifyEmail(Request $request): UserResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['code' => ['required', 'digits:6']], ['code.required' => 'Enter the 6-digit code.', 'code.digits' => 'Enter the 6-digit code.']);

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
    public function password(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [...Rules::password(), 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required'],
            'sign_out_other_devices' => ['sometimes', 'boolean'],
        ], [
            'current_password.required' => 'Enter your current password.',
            'password.required' => 'Meet all the rules below.',
            'password.min' => 'Meet all the rules below.',
            'password.regex' => 'Meet all the rules below.',
            'password.different' => 'Meet all the rules below.',
            'password.confirmed' => 'Passwords don’t match.',
            'password_confirmation.required' => 'Confirm your new password.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        }

        $user->forceFill(['password' => $data['password'], 'password_changed_at' => now()])->save();

        $signOut = $request->boolean('sign_out_other_devices', true);
        if ($signOut) {
            $current = \App\Support\Tokens::currentId($user);
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
    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:DELETE'],
        ], [
            'password.required' => 'Enter your password.',
            'confirmation.required' => 'Type DELETE in capital letters.',
            'confirmation.in' => 'It must say DELETE exactly.',
        ]);

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['That password is incorrect.']]);
        }

        Activity::log(null, 'account.deleted', "Deleted account {$user->email}", null, ['user_id' => $user->id, 'email' => $user->email, 'name' => $user->name]);

        $goodbye = new AccountDeletedMail(
            $user->email,
            $user->kitchenDishes()->count(),
            $user->mealLogs()->count(),
            Media::query()->where('model_type', (new KitchenDish)->getMorphClass())->whereIn('model_id', $user->kitchenDishes()->select('id'))->count(),
        );

        $user->tokens()->delete();
        $user->delete();

        Mailer::notice($goodbye->email, $goodbye);

        return response()->noContent();
    }
}
