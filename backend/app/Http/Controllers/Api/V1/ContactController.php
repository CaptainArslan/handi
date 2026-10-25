<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

/**
 * @tags Contact
 */
class ContactController extends Controller
{
    /**
     * Send a message to the Handi team
     *
     * Open to everyone (signed in or not), so people who can't log in can still reach us.
     * Send `multipart/form-data` when attaching a file (one file, up to 2 MB: image, PDF, TXT or Word),
     * JSON otherwise. With a token the message is linked to the account.
     *
     * The message is emailed to the team (replies go to `email`) and kept in the admin panel.
     * Limited to 5 messages an hour per person.
     *
     * @unauthenticated
     */
    public function __invoke(ContactRequest $request, ContactService $contacts): JsonResponse
    {
        $contacts->submit($request->validated(), $request->file('attachment'), $request->user('sanctum'), $request);

        return response()->json(['message' => ContactService::THANKS], 201);
    }
}
