<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application approved</title>
</head>
<body style="font-family: Arial, sans-serif; color: #17202A; line-height: 1.5;">
    <p>Good day{{ $application->applicant?->full_name ? ', '.$application->applicant->full_name : '' }}.</p>
    <p>Your application <strong>{{ $application->application_no }}</strong> for <strong>{{ $application->program?->name }}</strong>
        @if($password)
            has been <strong>accepted</strong>. You may now sign in and complete the requirements in the applicant portal.
        @else
            has been <strong>approved</strong> for assistance.
        @endif
    </p>
    @if($password)
        <p>Use these credentials to sign in:</p>
        <p>
            Email: <strong>{{ $application->applicant?->email }}</strong><br>
            Temporary password: <strong>{{ $password }}</strong>
        </p>
        <p>Please change your password after you sign in.</p>
    @else
        <p>You may sign in to the applicant portal with your existing account to view the updated status.</p>
    @endif
    <p>Sign in at: {{ rtrim(config('app.url'), '/') }}/login</p>
    <p style="color: #64748B; font-size: 12px;">Local Youth Development Office of Nabua · Municipality of Nabua, Camarines Sur</p>
</body>
</html>
