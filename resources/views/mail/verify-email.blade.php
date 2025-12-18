<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmez votre adresse e-mail</title>
</head>
<body style="margin:0;padding:24px;background-color:#F5F9FB;font-family:'Inter',Arial,sans-serif;color:#0F172A;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background-color:#FFFFFF;border:1px solid #E2E8F0;border-radius:16px;padding:32px;box-shadow:0 20px 70px rgba(15,23,42,0.08);">
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                            <img src="{{ $logoUrl }}" alt="Serena" style="height:40px;width:auto;">
                            <div style="font-weight:700;font-size:18px;color:#0F172A;">Serena</div>
                        </div>
                        <h1 style="margin:0 0 12px;font-size:24px;color:#0F172A;">Confirmez votre adresse e-mail</h1>
                        <p style="margin:0 0 16px;color:#0F172A;line-height:1.6;">Bonjour {{ $userName }},</p>
                        <p style="margin:0 0 16px;color:#0F172A;line-height:1.6;">Merci de rejoindre Serena. Pour sécuriser votre compte, validez votre adresse e-mail en cliquant sur le bouton ci-dessous.</p>
                        <div style="margin:24px 0;">
                            <a href="{{ $verificationUrl }}" style="background-color:#25B0EB;color:#FFFFFF;text-decoration:none;padding:14px 24px;border-radius:12px;font-weight:700;display:inline-block;">Vérifier mon adresse</a>
                        </div>
                        <p style="margin:0 0 12px;color:#0F172A;line-height:1.6;">Ce lien reste valable pendant 60 minutes. Si vous n'êtes pas à l'origine de cette inscription, vous pouvez ignorer ce message.</p>
                        <p style="margin:16px 0 12px;color:#64748B;font-size:13px;line-height:1.6;">Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :</p>
                        <p style="margin:0 0 24px;color:#0F172A;font-size:13px;line-height:1.6;word-break:break-all;">{{ $verificationUrl }}</p>
                        <p style="margin:0;color:#0F172A;font-weight:600;">À bientôt,</p>
                        <p style="margin:4px 0 0;color:#0F172A;">L'équipe Serena</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding-top:16px;">
            <p style="margin:0;color:#64748B;font-size:12px;">Hospitalité plus simple et plus fluide avec Serena.</p>
        </td>
    </tr>
</table>
</body>
</html>
