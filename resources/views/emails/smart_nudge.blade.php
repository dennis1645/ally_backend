@php
    // Setup teks, ikon, dan warna dinamis berdasarkan konteks tanggal
    $icon = '⏳';
    $title = 'Pengingat Tenggat Waktu';
    $message = '';
    $badgeLabel = 'Pengingat';
    $colorFrom = '#4F46E5'; $colorVia = '#6366F1'; $colorTo = '#3B82F6';
    $badgeBg = '#E0E7FF'; $badgeText = '#3730A3';

    if ($context === 'H-3') {
        $icon = '🚀';
        $title = '3 Hari Lagi! Kamu Pasti Bisa.';
        $message = 'Waktu berjalan cepat, tapi kamu masih punya cukup waktu. Yuk, mulai cicil dari sekarang agar tidak menumpuk di akhir!';
        $badgeLabel = '3 Hari Menuju Tenggat';
        $colorFrom = '#4F46E5'; $colorVia = '#6366F1'; $colorTo = '#3B82F6';
        $badgeBg = '#E0E7FF'; $badgeText = '#3730A3';
    } elseif ($context === 'H-1') {
        $icon = '🔥';
        $title = 'Besok Banget! Let\'s Finish It.';
        $message = 'Ini adalah dorongan terakhirmu! Tenggat waktu tinggal besok. Ayo segera selesaikan dan amankan XP Gamifikasi milikmu.';
        $badgeLabel = '1 Hari Menuju Tenggat';
        $colorFrom = '#EA580C'; $colorVia = '#F97316'; $colorTo = '#FB923C';
        $badgeBg = '#FFEDD5'; $badgeText = '#9A3412';
    } elseif ($context === 'Hari H') {
        $icon = '🎯';
        $title = 'Hari Ini Tenggat Waktunya!';
        $message = 'Waktu penentuan telah tiba. Pastikan kamu menyelesaikan tugas ini hari ini sebelum waktunya benar-benar habis. Fokus dan selesaikan!';
        $badgeLabel = 'Tenggat Hari Ini';
        $colorFrom = '#B91C1C'; $colorVia = '#DC2626'; $colorTo = '#EF4444';
        $badgeBg = '#FEE2E2'; $badgeText = '#B91C1C';
    } elseif ($context === 'H+1') {
        $icon = '❄️';
        $title = 'Oops Terlewat, Tapi Streak-mu Aman!';
        $message = 'Sepertinya kamu melewatkan tenggat waktu kemarin. Jangan khawatir, kami memahami situasimu. Kami telah membekukan Streak-mu (Streak Freeze) agar kerja kerasmu selama ini tidak hangus menjadi nol. Yuk, selesaikan sekarang untuk mencairkan kembali Streak-mu!';
        $badgeLabel = 'Tenggat Terlewat';
        $colorFrom = '#0E7490'; $colorVia = '#0891B2'; $colorTo = '#22D3EE';
        $badgeBg = '#CFFAFE'; $badgeText = '#0E7490';
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Pengingat: {{ $itemName }}</title>
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
        background: linear-gradient(135deg, {{ $colorFrom }} 0%, {{ $colorVia }} 50%, {{ $colorTo }} 100%);
        padding: 40px 32px;
        text-align: center;
    }
    .logo-badge {
        display: inline-block;
        background-color: rgba(255,255,255,0.15);
        border-radius: 50%;
        width: 68px;
        height: 68px;
        line-height: 68px;
        text-align: center;
        font-size: 32px;
        margin-bottom: 14px;
    }
    .header h1 {
        color: #ffffff;
        margin: 0;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: 0.2px;
        line-height: 1.4;
    }
    .header p {
        color: rgba(255,255,255,0.85);
        margin: 8px 0 0;
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

    .status-center { text-align: center; margin-bottom: 22px; }
    .status-badge {
        display: inline-block;
        font-size: 12.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 7px 16px;
        border-radius: 999px;
        color: {{ $badgeText }};
        background-color: {{ $badgeBg }};
    }

    .task-box {
        background-color: #F9FAFB;
        border-left: 4px solid {{ $colorFrom }};
        padding: 18px 20px;
        margin: 4px 0 24px;
        border-radius: 0 10px 10px 0;
    }
    .task-type {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #6B7280;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .task-title {
        font-size: 18px;
        font-weight: 700;
        color: #111827;
    }

    .streak-box {
        background-color: #ECFEFF;
        border: 1px solid #A5F3FC;
        border-radius: 10px;
        padding: 16px 20px;
        margin: 0 0 24px;
    }
    .streak-box p {
        font-size: 14px;
        color: #0E7490;
        margin: 0;
        line-height: 1.65;
    }
    .streak-box strong { color: #0891B2; }

    .btn-container { text-align: center; margin: 8px 0 8px; }
    .btn {
        display: inline-block;
        background: linear-gradient(135deg, {{ $colorFrom }}, {{ $colorTo }});
        color: #ffffff !important;
        text-decoration: none;
        padding: 15px 40px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 16px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
    }

    .footer {
        background-color: #F9FAFB;
        padding: 26px 32px;
        text-align: center;
        border-top: 1px solid #E5E7EB;
    }
    .footer p {
        margin: 0 0 4px;
        font-size: 12.5px;
        color: #9CA3AF;
        line-height: 1.6;
    }
    .footer p:last-child { margin-bottom: 0; }
    .footer .brand { font-weight: 700; color: #6B7280; }

    @media only screen and (max-width: 600px) {
        .content { padding: 30px 22px 22px !important; }
        .header { padding: 32px 20px !important; }
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
                            <div class="logo-badge">{{ $icon }}</div>
                            <h1>{{ $title }}</h1>
                            <p>Scholarship AI Platform</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <p>Halo, <strong>{{ $user->name }}</strong>,</p>

                            <div class="status-center">
                                <span class="status-badge">{{ $badgeLabel }}</span>
                            </div>

                            <p>{!! $message !!}</p>

                            <div class="task-box">
                                <div class="task-type">{{ $itemType }}</div>
                                <div class="task-title">{{ $itemName }}</div>
                            </div>

                            @if($context === 'H+1')
                                <div class="streak-box">
                                    <p>
                                        ❄️ <strong>Streak Freeze aktif</strong> — progres dan konsistensimu
                                        tetap aman selama ini. Selesaikan sekarang untuk mencairkannya
                                        kembali dan lanjutkan perjalananmu.
                                    </p>
                                </div>
                            @endif

                            <p>Klik tombol di bawah ini untuk langsung menuju platform dan menyelesaikan misimu.</p>

                            <div class="btn-container">
                                <a href="{{ env('FRONTEND_URL', 'http://localhost:5173') }}/dashboard" class="btn">
                                    Lanjutkan Perjalananmu
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p>Email ini dikirim secara otomatis oleh Sistem Cerdas Mentor Beasiswamu.</p>
                            <p>
                                <span class="brand">&copy; {{ date('Y') }} Scholarship AI Platform.</span>
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