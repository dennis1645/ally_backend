<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Permintaan Booking Konsultasi Baru</title>
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
        background: linear-gradient(135deg, #0E7490 0%, #0891B2 50%, #22D3EE 100%);
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
    .content p {
        font-size: 15.5px;
        line-height: 1.7;
        color: #4B5563;
        margin: 0 0 20px;
    }

    .status-center { text-align: center; margin-bottom: 20px; }
    .status-badge {
        display: inline-block;
        background-color: #FEF3C7;
        color: #92400E;
        font-size: 12.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 7px 16px;
        border-radius: 999px;
    }

    .info-box {
        background-color: #ECFEFF;
        border: 1px solid #A5F3FC;
        border-radius: 10px;
        padding: 20px 22px;
        margin: 4px 0 24px;
    }
    .info-box .info-label {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #0E7490;
        margin: 0 0 10px;
    }
    .info-list { margin: 0; padding: 0; list-style: none; }
    .info-list li {
        font-size: 14.5px;
        color: #374151;
        padding: 7px 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .info-list li:last-child { border-bottom: none; }
    .info-list li strong { color: #111827; font-weight: 600; }
    .info-list .booking-id {
        font-family: 'Courier New', monospace;
        background-color: #ffffff;
        padding: 2px 8px;
        border-radius: 4px;
        color: #0E7490;
        font-weight: 700;
    }

    .action-note {
        background-color: #F9FAFB;
        border: 1px solid #E5E7EB;
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 24px;
    }
    .action-note p {
        font-size: 14.5px;
        color: #4B5563;
        margin: 0;
        line-height: 1.7;
    }
    .action-note strong { color: #111827; }

    .signature { font-size: 15px; color: #4B5563; margin-top: 4px !important; }

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
                            <div class="logo-badge">📩</div>
                            <h1>Platform Persiapan Beasiswa</h1>
                            <p>Notifikasi Mentor</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <p>Halo Mentor <strong>{{ $mentorName }}</strong>,</p>

                            <div class="status-center">
                                <span class="status-badge">🔔 Booking Baru — Menunggu Konfirmasi</span>
                            </div>

                            <p>
                                Anda menerima permintaan booking baru dari seorang mentee untuk
                                sesi konsultasi 1-on-1.
                            </p>

                            <div class="info-box">
                                <p class="info-label">Detail Permintaan Sesi</p>
                                <ul class="info-list">
                                    <li>👤 Nama Mentee: <strong>{{ $menteeName }}</strong></li>
                                    <li>✉️ Email: <strong>{{ $menteeEmail }}</strong></li>
                                    <li>📅 Tanggal: <strong>{{ $date }}</strong></li>
                                    <li>🕒 Waktu: <strong>{{ $startTime }} - {{ $endTime }} WIB</strong></li>
                                    <li>🔖 ID Booking: <span class="booking-id">#{{ $bookingId }}</span></li>
                                </ul>
                            </div>

                            <div class="action-note">
                                <p>
                                    ➡️ Silakan buka <strong>Portal Mentor</strong> Anda untuk meninjau profil
                                    mentee, lalu lakukan <strong>Konfirmasi (Acc)</strong> dengan memasukkan
                                    link meeting, atau lakukan <strong>Reschedule / Reject</strong> jika diperlukan.
                                </p>
                            </div>

                            <p>Terima kasih atas dedikasi Anda membimbing para pejuang beasiswa! 🙌</p>

                            <p class="signature">
                                Salam hangat,<br>
                                <strong>Tim Platform Beasiswa</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p>
                                <span class="brand">&copy; {{ date('Y') }} Platform Beasiswa.</span><br>
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