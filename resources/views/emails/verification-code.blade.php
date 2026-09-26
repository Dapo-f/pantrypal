<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Hi {{ $user->username }},</h2>
    <p>Your verification code is:</p>
    <h1 style="letter-spacing: 4px;">{{ $code }}</h1>
    <p>This code expires in 10 minutes.</p>
    <p>If you didn't request this, ignore this email.</p>
</body>
</html>