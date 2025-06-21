<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;

class CustomVerifyEmail extends VerifyEmailBase
{
    /**
     * Get the verification URL for the given notifiable.
     */
    protected function verificationUrl($notifiable)
    {
        // Generate URL yang akan redirect ke frontend
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        // Generate signed URL untuk API
        $apiUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Parse URL untuk mendapatkan parameter
        $parsedUrl = parse_url($apiUrl);
        parse_str($parsedUrl['query'], $params);

        // Buat URL frontend dengan parameter verification
        $verificationUrl = $frontendUrl . '/auth/verify-email?' . http_build_query([
            'id' => $params['id'],
            'hash' => $params['hash'],
            'expires' => $params['expires'],
            'signature' => $params['signature']
        ]);

        return $verificationUrl;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $appName = Config::get('app.name', 'Learning Management System');

        return (new MailMessage)
            ->subject('Verifikasi Email Anda - ' . $appName)
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line('Terima kasih telah mendaftar di ' . $appName . '.')
            ->line('Silakan klik tombol di bawah ini untuk memverifikasi alamat email Anda.')
            ->action('Verifikasi Email', $verificationUrl)
            ->line('Link verifikasi ini akan kedaluwarsa dalam 60 menit.')
            ->line('Jika Anda tidak membuat akun, tidak ada tindakan lebih lanjut yang diperlukan.')
            ->salutation('Salam,')
            ->salutation('Tim ' . $appName);
    }
}
