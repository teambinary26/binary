<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application accepted</title>
</head>
<body style="font-family: Arial, sans-serif; color: #17202A; line-height: 1.5;">
    <p>Good day{{ $application->applicant?->full_name ? ', '.$application->applicant->full_name : '' }}.</p>
    <p>Your application <strong>{{ $application->application_no }}</strong> for <strong>{{ $application->program?->name }}</strong> has been <strong>accepted</strong>.</p>
    <p>You may now sign in to the applicant portal to complete the requirements, review your application, and submit it to the office.</p>
    <p>Use these sign-in details:</p>
    <p>
        Email: <strong>{{ $loginEmail }}</strong><br>
        Temporary password: <strong>{{ $password }}</strong>
    </p>
    <p>Please change your password after you sign in.</p>
    <p>Sign in at: {{ rtrim(config('app.url'), '/') }}/login</p>
    <p style="color: #64748B; font-size: 12px;">Local Youth Development Office of Nabua · Municipality of Nabua, Camarines Sur</p>
</body>
</html>
