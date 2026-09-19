<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'status' => $user->status->value,
            'preferred_locale' => $user->preferred_locale,
            'phone' => $user->phone,
        ];
    }
}
