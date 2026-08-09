<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Your Ticket Has Been Resolved</title>
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
        background: linear-gradient(135deg, #15803D 0%, #16A34A 50%, #4ADE80 100%);
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
        background-color: #D1FAE5;
        color: #065F46;
        font-size: 12.5px;
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        padding: 7px 16px;
        border-radius: 999px;
    }

    .ticket-number {
        font-family: 'Courier New', monospace;
        background-color: #F1F5F9;
        color: #16A34A;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 6px;
        white-space: nowrap;
    }

    .closing-note-box {
        background-color: #F0FDF4;
        border-left: 4px solid #16A34A;
        border-radius: 0 10px 10px 0;
        padding: 20px 22px;
        margin: 4px 0 24px;
    }
    .closing-note-label {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #15803D;
        font-weight: 700;
        margin: 0 0 10px;
    }
    .closing-note-text {
        font-size: 15px;
        color: #14532D;
        font-style: italic;
        line-height: 1.7;
        margin: 0;
    }

    .signature { font-size: 15px; color: #4B5563; margin-top: 8px !important; }

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
                            <div class="logo-badge">✅</div>
                            <h1>Ticket Resolved</h1>
                            <p>Ally Support</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <p>Hi <strong>{{ $ticket->user->name }}</strong>,</p>

                            <div class="status-center">
                                <span class="status-badge">✅ Resolved</span>
                            </div>

                            <p>
                                Good news! Your support ticket
                                <span class="ticket-number">#{{ $ticket->ticket_number }}</span>
                                has been marked as <strong style="color: #16A34A;">resolved</strong>.
                            </p>

                            @if(!empty($finalMessage))
                            <div class="closing-note-box">
                                <p class="closing-note-label">Closing Note from Admin</p>
                                <p class="closing-note-text">"{!! nl2br(e($finalMessage)) !!}"</p>
                            </div>
                            @endif

                            <p>
                                Thank you for reaching out to us. Feel free to open a new ticket
                                anytime if you have further questions or run into other issues.
                            </p>

                            <p class="signature">
                                Warm regards,<br>
                                <strong>The Ally Support Team</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer">
                            <p>
                                <span class="brand">&copy; {{ date('Y') }} Ally Platform.</span><br>
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