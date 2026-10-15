<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Activation or password recovery link sent to the registered campus email.
 */
class AccountLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $purpose)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $activation = $this->purpose === 'activation';
        $url = url(($activation ? '/aktivasi/' : '/reset-sandi/').$this->token.'?email='.urlencode($notifiable->email));
        $minutes = $activation ? config('brudel.activation_expire_minutes') : config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject($activation ? 'Aktivasi akun BRUDEL' : 'Pemulihan kata sandi BRUDEL')
            ->greeting('Halo, '.$notifiable->name)
            ->line($activation
                ? 'Akun BRUDEL Anda telah disiapkan dari data resmi kampus. Buat kata sandi untuk mengaktifkannya.'
                : 'Kami menerima permintaan pemulihan kata sandi untuk akun BRUDEL Anda.')
            ->action($activation ? 'Aktifkan akun' : 'Atur ulang kata sandi', $url)
            ->line('Tautan berlaku '.intdiv($minutes, 60).' jam. Abaikan email ini jika Anda tidak memintanya.')
            ->salutation('BRUDEL · Poltekkes Kemenkes Manado');
    }
}
