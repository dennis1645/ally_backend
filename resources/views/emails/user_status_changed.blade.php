<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Your Account Status</title>
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

    @if($status === 'suspended')
    .header { background: linear-gradient(135deg, #B91C1C 0%, #DC2626 50%, #EF4444 100%); }
    @else
    .header { background: linear-gradient(135deg, #15803D 0%, #16A34A 50%, #22C55E 100%); }
    @endif
    .header {
        padding: 40px 32px;
        text-align: center;
    }
    .logo-badge {
        display: inline-block;
        background-color: rgba(255,255,255,0.15);
        border-radius: 50%;
        width: 60px;
        height: 60px;
        line-height: 60px;
        text-align: center;
        font-size: 26px;
        margin-bottom: 14px;
    }
    .header h1 {
        color: #ffffff;
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        letter-spacing: 0.3px;
    }
    .header p {
        color: rgba(255,255,255,0.85);
        margin: 6px 0 0;
        font-size: 12.5px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    .content { padding: 40px 36px 32px; text-align: left; }
    .content h2 {
        margin: 0 0 16px;
        color: #111827;
        font-size: 20px;
        font-weight: 700;
        text-align: center;
    }
    .content p {
        font-size: 15.5px;
        line-height: 1.7;
        color: #4B5563;
        margin: 0 0 20px;
    }

    .status-center { text-align: center; margin-bottom: 20px; }
    .status-badge {
        display: inline-block;
        font-size: 12.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 7px 16px;
        border-radius: 999px;
    }
    .badge-suspended { background-color: #FEE2E2; color: #B91C1C; }
    .badge-active { background-color: #D1FAE5; color: #065F46; }

    .info-box {
        border-radius: 10px;
        padding: 18px 20px;
        margin: 4px 0 24px;
    }
    .box-suspended { background-color: #FEF2F2; border: 1px solid #FECACA; }
    .box-active { background-color: #F0FDF4; border: 1px solid #BBF7D0; }
    .info-box p {
        font-size: 14.5px;
        margin: 0;
        line-height: 1.65;
    }
    .box-suspended p { color: #991B1B; }
    .box-active p { color: #166534; }

    .form-link-box {
        background-color: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px 20px;
        margin: 0 0 24px;
    }
    .form-link-box p {
        font-size: 14.5px;
        color: #4B5563;
        margin: 0;
        line-height: 1.65;
    }
    .form-link-box a {
        color: #4F46E5;
        font-weight: 600;
        text-decoration: none;
        word-break: break-all;
    }

    .btn-container { text-align: center; margin: 8px 0 8px; }
    .btn {
        display: inline-block;
        color: #ffffff !important;
        text-decoration: none;
        padding: 15px 40px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 16px;
    }
    @if($status === 'suspended')
    .btn { background: linear-gradient(135deg, #6B7280, #4B5563); box-shadow: 0 8px 16px rgba(75, 85, 99, 0.25); }
    @else
    .btn { background: linear-gradient(135deg, #16A34A, #22C55E); box-shadow: 0 8px 16px rgba(22, 163, 74, 0.3); }
    @endif

    .signature { font-size: 15px; color: #4B5563; margin-top: 28px !important; }

    .footer {
        background-color: #F9FAFB;
        padding: 26px 32px;
        text-align: center;
        border-top: 1px solid #E5E7EB;
    }
    .footer p {
        margin: 0;
        font-size: 12.5px;
        color: #9CA3AF;
        line-height: 1.6;
    }
    .footer .brand { font-weight: 700; color: #6B7280; }

    @media only screen and (max-width: 600px) {
        .content { padding: 30px 22px 22px !important; }
        .header { padding: 30px 20px !important; }
        .footer { padding: 22px 20px !important; }
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
                            <div class="logo-badge">{{ $status === 'suspended' ? '⛔' : '✅' }}</div>
                            <h1>{{ config('app.name') }}</h1>
                            <p>Account Status</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <h2>Hi, {{ $user->name }}</h2>

                            @if($status === 'suspended')
                                <div class="status-center">
                                    <span class="status-badge badge-suspended">🔒 Account Suspended</span>
                                </div>

                                <p>
                                    Important notice regarding your account on
                                    <strong>{{ config('app.name') }}</strong>.
                                </p>

                                <div class="info-box box-suspended">
                                    <p>
                                        Your account has been <strong>suspended</strong> by an
                                        Administrator due to a policy adjustment or a violation
                                        of our platform's terms of service.
                                    </p>
                                </div>

                                <p>
                                    If you believe this is a mistake or need further assistance,
                                    please reach out to our support team.
                                </p>

                                <div class="form-link-box">
                                    <p>
                                        Please fill out the form below to submit your request:<br>
                                        <a href="https://forms.gle/H2dNK6Z7Shm9qoVb8" target="_blank">https://forms.gle/H2dNK6Z7Shm9qoVb8</a>
                                    </p>
                                </div>

                                <div class="btn-container">
                                    <a href="{{ url('/support') }}" class="btn">Contact Support</a>
                                </div>

                            @else
                                <div class="status-center">
                                    <span class="status-badge badge-active">✅ Account Reactivated</span>
                                </div>

                                <p>
                                    Good news! Your account on <strong>{{ config('app.name') }}</strong>
                                    has been reactivated by an Administrator.
                                </p>

                                <div class="info-box box-active">
                                    <p>
                                        You can now log in to the platform again and continue
                                        your scholarship preparation journey.
                                    </p>
                                </div>

                                <div class="btn-container">
                                    <a href="{{ url('/login') }}" class="btn">Log In to Your Account</a>
                                </div>
                            @endif

                            <p class="signature">
                                Thank you,<br>
                                <strong>The {{ config('app.name') }} Team</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p>
                                <span class="brand">&copy; {{ date('Y') }} {{ config('app.name') }}.</span><br>
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