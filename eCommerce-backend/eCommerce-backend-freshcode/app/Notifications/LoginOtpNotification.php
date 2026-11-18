<?php

namespace App\Notifications;

use App\Enums\SettingTypeEnum;
use App\Services\EmailOtpService;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code, public string $purpose = 'login')
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = app(SettingService::class);
        $system = $settings->getRawSetting(SettingTypeEnum::SYSTEM())?->value ?? [];

        $isReset = $this->purpose === 'reset';
        $subject = $isReset ? __('labels.reset_otp_subject') : __('labels.login_otp_subject');

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.auth-otp', [
                'subject' => $subject,
                'heading' => $isReset ? __('labels.otp_email_reset_heading') : __('labels.otp_email_login_heading'),
                'intro' => $isReset ? __('labels.otp_email_reset_intro') : __('labels.otp_email_login_intro'),
                'codeLabel' => $isReset ? __('labels.otp_email_reset_label') : __('labels.otp_email_login_label'),
                'ignoreNote' => $isReset ? __('labels.otp_email_reset_ignore') : __('labels.otp_email_login_ignore'),
                'code' => $this->code,
                'expiryMinutes' => (int) (EmailOtpService::TTL_SECONDS / 60),
                'accent' => '#4f46e5',
                'brandName' => $settings->getBrandName(),
                'logoUrl' => !empty($system['logo']) ? url('storage/' . $system['logo']) : asset('logos/hyper-logo.png'),
                'darkLogoUrl' => !empty($system['darkLogo']) ? url('storage/' . $system['darkLogo']) : null,
                'companyAddress' => trim((string) ($system['companyAddress'] ?? '')),
            ]);
    }
}
