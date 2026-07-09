<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $email = $notifiable->getEmailForPasswordReset();
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], false));

            $data = [
                'appName' => config('app.name', 'Sistem Presensi Tambang Loli'),
                'brandName' => 'PT. ABDARA BRM TAMBANG',
                'userName' => $notifiable->full_name ?? $notifiable->name ?? null,
                'resetUrl' => $resetUrl,
                'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                'logoUrl' => asset('images/logo.jpeg'),
            ];

            return (new MailMessage)
                ->subject('Reset Password Presensi Tambang Loli')
                ->view([
                    'html' => 'emails.auth.reset-password',
                    'text' => 'emails.auth.reset-password-text',
                ], $data);
        });
    }
}
