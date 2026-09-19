<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Identity\Actions\VerifyCustomerEmailAction;
use App\Domains\Identity\Models\User;
use App\Domains\Shared\Support\CorrelationId;
use App\Http\Requests\Api\V1\Auth\ResendVerificationRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EmailVerificationController
{
    public function verify(
        Request $request,
        string $id,
        string $hash,
        VerifyCustomerEmailAction $action,
    ): JsonResponse|RedirectResponse {
        $user = User::query()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new AuthorizationException('Invalid verification hash.');
        }

        $newlyVerified = $action->execute($user);
        $status = $newlyVerified ? 'success' : 'already';

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'status' => $status,
                    'email_verified' => true,
                ],
                'meta' => [
                    'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
                ],
            ]);
        }

        return redirect()->away($this->frontendUrl($status));
    }

    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'data' => [
                'status' => 'accepted',
            ],
            'meta' => [
                'request_id' => $request->attributes->get(CorrelationId::REQUEST_ATTRIBUTE),
            ],
        ], 202);
    }

    private function frontendUrl(string $status): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/verify-email?status='.$status;
    }
}
