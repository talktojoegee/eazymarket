<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body
    style="margin:0; padding:0; background:#eef0f3; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef0f3;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                   style="width:600px; max-width:100%; background:#fafafa; border:1px solid #e4e4e7; border-radius:12px;">
                <tr>
                    <td style="padding:28px;">

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="padding:6px 0 22px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                        <tr style="display: flex; flex-direction: column; justify-content: center; align-items: center;">
                                            <td valign="middle">
                                                @if(!empty($darkLogoUrl))
                                                    <picture>
                                                        <source srcset="{{ $darkLogoUrl }}" media="(prefers-color-scheme: dark)">
                                                        <img src="{{ $darkLogoUrl }}" alt="{{ $brandName }}" height="65"
                                                             style="display:block; height:65px; width:auto; border:0;">
                                                    </picture>
                                                @else
                                                    <img src="{{ $logoUrl }}" alt="{{ $brandName }}" height="65"
                                                         style="display:block; height:65px; width:auto; border:0;">
                                                @endif
                                            </td>
                                            <td valign="middle"
                                                style="padding-left:9px; font-size:17px; font-weight:700; color:#18181b; letter-spacing:-.01em;">{{ $brandName }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background:#ffffff; border:1px solid #e4e4e7; border-radius:8px;">
                            <tr>
                                <td style="padding:36px 40px;">
                                    <h1 style="margin:0 0 10px; font-size:20px; font-weight:700; color:#18181b; letter-spacing:-.01em;">{{ $heading }}</h1>
                                    <p style="margin:0 0 22px; font-size:15px; line-height:1.55; color:#52525b;">{{ $intro }}</p>

                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                           style="border:1px solid #e4e4e7; border-radius:10px; background:#fafafa;">
                                        <tr>
                                            <td align="center" style="padding:22px 24px;">
                                                <div
                                                    style="font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:#a1a1aa; margin-bottom:12px;">{{ $codeLabel }}</div>
                                                <div
                                                    style="font-size:38px; font-weight:700; letter-spacing:.34em; color:{{ $accent }}; padding-left:.34em;">{{ $code }}</div>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style="margin:20px 0 0; font-size:13.5px; line-height:1.5; color:#71717a;">{{ __('labels.otp_email_expiry_prefix') }}
                                        <strong
                                            style="color:#52525b;">{{ __('labels.otp_email_expiry_minutes', ['minutes' => $expiryMinutes]) }}</strong>.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style="border-top:1px solid #e4e4e7; padding:18px 40px; background:#fafafa; border-radius:0 0 8px 8px;">
                                    <p style="margin:0; font-size:12.5px; line-height:1.5; color:#a1a1aa;">{{ $ignoreNote }}</p>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td align="center" style="padding:22px 20px 4px;">
                                    <p style="margin:0 0 4px; font-size:12px; color:#a1a1aa;">{{ __('labels.otp_email_sent_by', ['brand' => $brandName]) }}</p>
                                    @if(!empty($companyAddress))
                                        <p style="margin:0; font-size:12px; color:#a1a1aa;">{{ $companyAddress }}</p>
                                    @endif
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
