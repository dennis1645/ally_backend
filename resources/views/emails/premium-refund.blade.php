<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Account Deletion & Premium Refund</title>
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
        background: linear-gradient(135deg, #DC2626 0%, #EF4444 50%, #F87171 100%);
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

    .content { padding: 44px 36px 32px; text-align: left; }
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
    .highlight {
        font-weight: 700;
        color: #111827;
    }

    .status-badge {
        display: inline-block;
        background-color: #FEE2E2;
        color: #B91C1C;
        font-size: 12.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 6px 14px;
        border-radius: 999px;
        margin-bottom: 8px;
    }
    .status-center { text-align: center; margin-bottom: 8px; }

    .refund-box {
        background-color: #F0FDF4;
        border: 1px solid #BBF7D0;
        border-radius: 10px;
        padding: 18px 20px;
        margin: 8px 0 24px;
    }
    .refund-box p {
        font-size: 14.5px;
        color: #166534;
        margin: 0;
        line-height: 1.6;
    }
    .refund-box .premium-tag {
        display: inline-block;
        background-color: #DCFCE7;
        color: #15803D;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 13.5px;
    }

    .divider { border: none; border-top: 1px solid #E5E7EB; margin: 8px 0 24px; }

    .contact-note {
        font-size: 13.5px;
        color: #9CA3AF;
        text-align: center;
        margin-top: 4px !important;
    }

    .signature {
        font-size: 15px;
        color: #4B5563;
        margin-top: 8px !important;
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

    @media only screen and (max-width: 600px) {
        .content { padding: 32px 24px 24px !important; }
        .header { padding: 32px 20px !important; }
        .footer { padding: 24px 20px !important; }
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
                            <div class="logo-badge">⚠️</div>
                            <h1>Account Deletion Notice</h1>
                            <p>Important Account Update</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <div class="status-center">
                                <span class="status-badge">Account Deleted</span>
                            </div>

                            <p>Dear <span class="highlight">{{ $user->name }}</span>,</p>

                            <p>
                                We are writing to inform you that your account on our platform
                                has been deleted by the administrator.
                            </p>

                            <div class="refund-box">
                                <p>
                                    ✅ Because you are an active <span class="premium-tag">Premium Member</span>,
                                    you are eligible for a refund according to our policy. Our support
                                    team will process this shortly.
                                </p>
                            </div>

                            <p>
                                If you have any questions or need to provide additional details for
                                the refund process, please reply directly to this email or contact
                                our support team.
                            </p>

                            <p class="signature">
                                Best regards,<br>
                                <strong>The Admin Team</strong>
                            </p>

                            <hr class="divider">

                            <p class="contact-note">
                                This is an automated message, but replies will be directed to our support team.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p>
                                <span class="brand">&copy; {{ date('Y') }} ALLY Platform.</span><br>
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