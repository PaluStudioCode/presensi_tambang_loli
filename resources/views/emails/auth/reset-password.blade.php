<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Reset Password</title>
</head>
<body style="margin:0; padding:0; background:#dbeafe; font-family:Arial, Helvetica, sans-serif; color:#0f172a;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">
        Gunakan tautan ini untuk membuat password baru akun presensi Anda.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; background:#dbeafe; margin:0; padding:0;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%; max-width:640px; border-collapse:collapse;">
                    <tr>
                        <td style="background:#172554; border-radius:18px 18px 0 0; padding:28px 28px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="64" valign="middle" style="width:64px;">
                                        <img src="{{ $logoUrl }}" alt="{{ $brandName }}" width="56" height="56" style="display:block; width:56px; height:56px; border-radius:12px; object-fit:cover; border:1px solid #bfdbfe; background:#ffffff;">
                                    </td>
                                    <td valign="middle" style="padding-left:14px;">
                                        <div style="font-size:13px; line-height:18px; color:#bfdbfe; font-weight:700; letter-spacing:.08em; text-transform:uppercase;">Portal Presensi</div>
                                        <div style="font-size:22px; line-height:28px; color:#ffffff; font-weight:800; margin-top:4px;">{{ $brandName }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#ffffff; padding:34px 28px 30px; border-left:1px solid #bfdbfe; border-right:1px solid #bfdbfe;">
                            <h1 style="margin:0; font-size:28px; line-height:36px; color:#172554; font-weight:800;">Reset Password Akun</h1>

                            <p style="margin:18px 0 0; font-size:16px; line-height:26px; color:#334155;">
                                Halo{{ $userName ? ' '.$userName : '' }},
                            </p>

                            <p style="margin:12px 0 0; font-size:16px; line-height:26px; color:#334155;">
                                Kami menerima permintaan untuk membuat password baru pada akun {{ $appName }}. Klik tombol di bawah ini untuk melanjutkan proses reset password.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 26px;">
                                <tr>
                                    <td align="center" bgcolor="#1d4ed8" style="border-radius:10px;">
                                        <a href="{{ $resetUrl }}" target="_blank" style="display:inline-block; padding:14px 24px; font-size:15px; line-height:20px; color:#ffffff; text-decoration:none; font-weight:800; background:#1d4ed8; border-radius:10px;">
                                            Reset Password Sekarang
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:12px; padding:16px 18px;">
                                <p style="margin:0; font-size:14px; line-height:22px; color:#1e3a8a;">
                                    Tautan reset password ini berlaku selama <strong>{{ $expireMinutes }} menit</strong>. Jika Anda tidak meminta reset password, abaikan email ini dan akun Anda tetap aman.
                                </p>
                            </div>

                            <p style="margin:24px 0 0; font-size:13px; line-height:21px; color:#64748b;">
                                Jika tombol tidak dapat dibuka, salin dan tempel tautan berikut ke browser Anda:
                            </p>
                            <p style="margin:8px 0 0; font-size:13px; line-height:21px; word-break:break-all;">
                                <a href="{{ $resetUrl }}" target="_blank" style="color:#1d4ed8; text-decoration:underline;">{{ $resetUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f8fafc; border:1px solid #bfdbfe; border-top:0; border-radius:0 0 18px 18px; padding:22px 28px;">
                            <p style="margin:0; font-size:13px; line-height:21px; color:#64748b;">
                                Email otomatis dari {{ $appName }}. Mohon tidak membalas email ini.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
