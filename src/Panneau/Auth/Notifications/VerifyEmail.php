<?php

namespace Panneau\Auth\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The email that verifies the address of an admin user, whose link opens the page of the app
 * (`{path}/auth/verify-email`). It has its own callbacks: `VerifyEmail::toMailUsing()` of the
 * site doesn't change it, and `toMailUsing()` on this class changes only it.
 */
class VerifyEmail extends BaseVerifyEmail
{
    /**
     * @var (\Closure(mixed, string): (MailMessage|Mailable))|null
     */
    public static $toMailCallback;

    /**
     * @var (\Closure(mixed): string)|null
     */
    public static $createUrlCallback;

    public function __construct(public readonly string $url) {}

    /**
     * @param  mixed  $notifiable
     */
    protected function verificationUrl($notifiable): string
    {
        return $this->url;
    }
}
