<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Application received</title>
</head>
<body style="font-family: Arial, sans-serif; color: #17202A; line-height: 1.5;">
    <p>Good day{{ $application->applicant?->full_name ? ', '.$application->applicant->full_name : '' }}.</p>
    <p>Your application for <strong>{{ $application->program?->name }}</strong> has been successfully received. Your application number is:</p>
    <p style="font-size: 22px; font-weight: bold; color: #002D62;">{{ $application->application_no }}</p>
    <p>Please keep this number for your reference. Our office will review the details you submitted.</p>
    <p><strong>What happens next?</strong></p>
    <ol>
        <li>An administrator will review your application.</li>
        <li>Once your application is accepted, you will receive another email with your sign-in email and temporary password for the applicant portal.</li>
        <li>You can then sign in to upload the required documents, review your application, and submit it to the office.</li>
    </ol>
    <p>You do not need to do anything else at this time. Please watch your inbox (and spam folder) for the approval email.</p>
    <p style="color: #64748B; font-size: 12px;">Local Youth Development Office of Nabua · Municipality of Nabua, Camarines Sur</p>
</body>
</html>
