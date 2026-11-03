<?php

namespace Panneau\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The email of a password reset of the admin, whose link opens the page of the app
 * (`{path}/auth/reset-password`). It has its own callbacks: `ResetPassword::toMailUsing()` of
 * the site doesn't change it, and `toMailUsing()` on this class changes only it.
 */
class ResetPassword extends BaseResetPassword
{
    /**
     * @var (\Closure(mixed, string): (MailMessage|Mailable))|null
     */
    public static $toMailCallback;

    /**
     * @var (\Closure(mixed, string): string)|null
     */
    public static $createUrlCallback;

    public function __construct(#[\SensitiveParameter] $token, public readonly string $url)
    {
        parent::__construct($token);
    }

    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }
}
