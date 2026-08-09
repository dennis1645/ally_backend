<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>New Reply to Your Support Ticket</title>
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
        background: linear-gradient(135deg, #0369A1 0%, #0284C7 50%, #38BDF8 100%);
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
        background-color: #E0F2FE;
        color: #0369A1;
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
        color: #0284C7;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 6px;
        white-space: nowrap;
    }

    .reply-box {
        background-color: #F0F9FF;
        border-left: 4px solid #0284C7;
        border-radius: 0 10px 10px 0;
        padding: 20px 22px;
        margin: 4px 0 24px;
    }
    .reply-label {
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #0369A1;
        font-weight: 700;
        margin: 0 0 10px;
    }
    .reply-text {
        font-size: 15px;
        color: #1E293B;
        font-style: italic;
        line-height: 1.7;
        margin: 0;
    }

    .payment-note {
        background-color: #FFFBEB;
        border: 1px solid #FDE68A;
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 8px;
    }
    .payment-note p {
        font-size: 14px;
        color: #92400E;
        margin: 0;
        line-height: 1.65;
    }
    .payment-note strong { color: #78350F; }

    .signature { font-size: 15px; color: #4B5563; margin-top: 20px !important; }

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
                            <div class="logo-badge">💬</div>
                            <h1>New Reply to Your Ticket</h1>
                            <p>Ally Support</p>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td class="content">
                            <p>Hi <strong>{{ $ticket->user->name }}</strong>,</p>

                            <div class="status-center">
                                <span class="status-badge">Admin Replied</span>
                            </div>

                            <p>
                                Our admin has responded to your ticket
                                <span class="ticket-number">#{{ $ticket->ticket_number }}</span>.
                            </p>

                            <div class="reply-box">
                                <p class="reply-label">Message from Admin</p>
                                <p class="reply-text">"{!! nl2br(e($replyMessage)) !!}"</p>
                            </div>

                            <div class="payment-note">
                                <p>
                                    💳 <strong>Payment-related issue?</strong> Please make sure you've
                                    included your Midtrans Order ID so we can sync your data correctly.
                                </p>
                            </div>

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