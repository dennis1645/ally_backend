<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Revisi Tugas Mentee Diterima</title>
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
        margin: 0 0 24px;
    }
    .highlight {
        font-weight: 700;
        color: #4F46E5;
        background-color: #EEF2FF;
        padding: 2px 8px;
        border-radius: 6px;
        white-space: nowrap;
    }

    .summary-box {
        background-color: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        padding: 20px 24px;
        text-align: left;
        margin-bottom: 28px;
    }
    .summary-row {
        padding: 8px 0;
        border-bottom: 1px dashed #E5E7EB;
    }
    .summary-row:last-child {
        border-bottom: none;
    }
    .summary-label {
        font-size: 13px;
        color: #6B7280;
        font-weight: 600;
    }
    .summary-value {
        font-size: 14.5px;
        color: #111827;
        font-weight: 700;
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
                            <div class="logo-badge">📬</div>
                            <h1>ALLY Portal Mentor</h1>
                            <p>Tugas Mentee Siap Diperiksa</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <h2>Halo, {{ $mentor->name }}! 👋</h2>
                            <p>
                                Mentee Anda (<strong>{{ $mentee->name }}</strong>) telah mengirimkan perbaikan/revisi untuk tugas <span class="highlight">{{ $milestone->task_name }}</span>.
                            </p>

                            <div class="summary-box">
                                <div class="summary-row">
                                    <span class="summary-label">Nama Mentee:</span><br>
                                    <span class="summary-value">{{ $mentee->name }} ({{ $mentee->email }})</span>
                                </div>
                                <div class="summary-row">
                                    <span class="summary-label">Nama Task:</span><br>
                                    <span class="summary-value">{{ $milestone->task_name }}</span>
                                </div>
                                @if(!empty($submission->file_name))
                                <div class="summary-row">
                                    <span class="summary-label">Berkas Lampiran Terbaru:</span><br>
                                    <span class="summary-value">{{ $submission->file_name }}</span>
                                </div>
                                @endif
                                <div class="summary-row">
                                    <span class="summary-label">Waktu Pengiriman:</span><br>
                                    <span class="summary-value">{{ $submission->updated_at->format('Y-m-d H:i:s') }}</span>
                                </div>
                            </div>

                            <p style="font-size: 14px; color: #6B7280;">
                                Silakan masuk ke Portal Mentor untuk meninjau dan menyetujui tugas mentee.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p style="margin-bottom: 14px;">
                                Email notifikasi ini dikirim secara otomatis oleh Sistem ALLY Mentor Portal.
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
