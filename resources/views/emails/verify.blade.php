<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Verify Your Email</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style>
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    body { margin: 0; padding: 0; width: 100% !important; height: 100% !important; }

    body, .body-bg {
        font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
        background-color: #EEF1F6;
        color: #374151;
    }

    .wrapper { width: 100%; background-color: #EEF1F6; padding: 48px 16px; }

    .main {
        background-color: #ffffff;
        margin: 0 auto;
        width: 100%;
        max-width: 560px;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(17, 24, 39, 0.08);
    }

    .header {
        background: linear-gradient(135deg, #4F46E5 0%, #6366F1 50%, #3B82F6 100%);
        padding: 44px 32px;
        text-align: center;
    }
    .logo-badge {
        display: inline-block;
        background-color: rgba(255,255,255,0.15);
        border-radius: 50%;
        width: 64px;
        height: 64px;
        line-height: 64px;
        text-align: center;
        font-size: 28px;
        margin-bottom: 16px;
    }
    .header h1 {
        color: #ffffff;
        margin: 0;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .header p {
        color: rgba(255,255,255,0.85);
        margin: 6px 0 0;
        font-size: 13px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .content { padding: 44px 36px 32px; text-align: center; }
    .content h2 {
        margin: 0 0 16px;
        color: #111827;
        font-size: 22px;
        font-weight: 700;
    }
    .content p {
        font-size: 15.5px;
        line-height: 1.7;
        color: #4B5563;
        margin: 0 0 28px;
    }
    .highlight {
        font-weight: 700;
        color: #4F46E5;
        background-color: #EEF2FF;
        padding: 2px 8px;
        border-radius: 6px;
        white-space: nowrap;
    }

    .btn-container { margin: 8px 0 28px; }
    .btn {
        display: inline-block;
        background: linear-gradient(135deg, #4F46E5, #6366F1);
        color: #ffffff !important;
        text-decoration: none;
        padding: 15px 40px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 16px;
        box-shadow: 0 8px 16px rgba(79, 70, 229, 0.3);
    }

    .divider { border: none; border-top: 1px solid #E5E7EB; margin: 8px 0 24px; }

    .fallback-box {
        background-color: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px 18px;
        text-align: left;
        margin-bottom: 8px;
    }
    .fallback-box p {
        font-size: 13px;
        color: #9CA3AF;
        margin: 0 0 8px;
        line-height: 1.5;
    }
    .fallback-box a {
        color: #4F46E5;
        word-break: break-all;
        font-size: 13px;
        text-decoration: none;
    }

    .expiry-note {
        font-size: 13px;
        color: #9CA3AF;
        margin-top: 20px !important;
    }

    .footer {
        background-color: #F9FAFB;
        padding: 28px 32px;
        text-align: center;
        border-top: 1px solid #E5E7EB;
    }
    .footer p {
        margin: 0;
        font-size: 12.5px;
        color: #9CA3AF;
        line-height: 1.6;
    }
    .footer .brand {
        font-weight: 700;
        color: #6B7280;
    }
    .social-links { margin-bottom: 14px; }
    .social-links a {
        display: inline-block;
        margin: 0 6px;
        color: #6B7280;
        text-decoration: none;
        font-size: 12.5px;
    }

    @media only screen and (max-width: 600px) {
        .content { padding: 32px 24px 24px !important; }
        .header { padding: 32px 20px !important; }
        .footer { padding: 24px 20px !important; }
        .btn { display: block !important; padding: 15px 0 !important; }
    }
</style>
</head>
<body class="body-bg">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="wrapper">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" class="main">
                    <!-- Header -->
                    <tr>
                        <td class="header">
                            <div class="logo-badge">🎓</div>
                            <h1>ALLY Platform</h1>
                            <p>Scholarship &amp; Mentorship</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <h2>Welcome aboard, {{ $user->name }}! 👋</h2>
                            <p>
                                You're one step away from unlocking your full potential.
                                Verify your email to activate your account, connect with
                                expert mentors, and start earning <span class="highlight">XP Points</span>
                                on your scholarship journey.
                            </p>

                            <div class="btn-container">
                                <a href="{{ $url }}" class="btn">Verify Email Address</a>
                            </div>

                            <hr class="divider">

                            <div class="fallback-box">
                                <p>Button not working? Copy and paste this link into your browser:</p>
                                <a href="{{ $url }}">{{ $url }}</a>
                            </div>

                            <p class="expiry-note">
                                ⏱️ This verification link will expire in 60 minutes for your security.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p style="margin-bottom: 14px;">
                                If you didn't create an account with ALLY, you can safely ignore this email —
                                no further action is needed.
                            </p>
                            <p>
                                <span class="brand">&copy; {{ date('Y') }} ALLY Scholarship Mentorship.</span><br>
                                All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>