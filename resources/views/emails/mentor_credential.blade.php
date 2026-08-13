<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Akun Mentor Anda Telah Dibuat</title>
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

    .credential-box {
        background-color: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 20px 24px;
        text-align: left;
        margin-bottom: 28px;
    }
    .credential-label {
        font-size: 13.5px;
        color: #6B7280;
        font-weight: 600;
    }
    .credential-value {
        font-size: 14.5px;
        color: #111827;
        font-weight: 700;
    }
    .password-badge {
        font-family: monospace;
        font-size: 15px;
        background-color: #EEF2FF;
        color: #4F46E5;
        padding: 4px 10px;
        border-radius: 6px;
        letter-spacing: 0.5px;
        font-weight: bold;
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
                            <div class="logo-badge">👨‍🏫</div>
                            <h1>ALLY Portal Mentor</h1>
                            <p>Scholarship &amp; Mentorship</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <h2>Selamat Datang, {{ $mentor->name }}! 👋</h2>
                            <p>
                                Akun Mentor Anda telah berhasil didaftarkan di platform <span class="highlight">ALLY</span>.
                                Gunakan informasi kredensial di bawah ini untuk masuk ke Portal Mentor dan mulai membimbing mentee Anda.
                            </p>

                            <div class="credential-box">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px dashed #E5E7EB;">
                                            <span class="credential-label">Email Login</span><br>
                                            <span class="credential-value">{{ $mentor->email }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px 0 8px;">
                                            <span class="credential-label">Password Sementara</span><br>
                                            <span class="password-badge">{{ $plainPassword }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <p class="expiry-note">
                                🔐 Untuk keamanan, Anda dapat memperbarui password setelah melakukan login pertama kali.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p style="margin-bottom: 14px;">
                                Email kredensial ini dikirim secara otomatis oleh Sistem ALLY Mentor Matching.
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
