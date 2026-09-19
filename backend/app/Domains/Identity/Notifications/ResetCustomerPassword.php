<?php

declare(strict_types=1);

namespace App\Domains\Identity\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class ResetCustomerPassword extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $expiresInMinutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your password')
            ->line('We received a request to reset the password for your account.')
            ->action('Reset password', $url)
            ->line("This link expires in {$expiresInMinutes} minutes.")
            ->line('If you did not request a password reset, no further action is required.');
    }

    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        $frontendUrl = (string) config('app.frontend_url');

        return rtrim($frontendUrl, '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
