<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verification code</title>
</head>
<body style="font-family: Arial, sans-serif; color: #17202A; line-height: 1.5;">
    <p>Good day{{ $applicantName ? ', '.$applicantName : '' }}.</p>
    <p>Your one-time verification code for the Local Youth Development Office of Nabua assistance application is:</p>
    <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #002D62;">{{ $otp }}</p>
    <p>This code expires in 10 minutes. Do not share it with anyone.</p>
    <p style="color: #64748B; font-size: 12px;">This is an official message of the Municipality of Nabua. Services are free of charge.</p>
</body>
</html>
