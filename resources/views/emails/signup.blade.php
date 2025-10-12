<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} Signup</title>
    <meta http-equiv="x-ua-compatible" content="ie=edge">
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;">
        <tr>
            <td align="center" style="padding:24px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:100%; background:#ffffff; border:1px solid #e5e7eb; border-radius:6px; overflow:hidden;">
                    <tr>
                        <td style="padding:16px 20px; background:#111827; color:#ffffff; font-family:Arial, Helvetica, sans-serif; font-size:16px;">
                            <strong>{{ config('app.name') }}</strong> — Newsletter Signup
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#111827;">
                            {!! email_table($formData) !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 20px; font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#6b7280; background:#f9fafb;">
                            Sent automatically by {{ config('app.name') }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

