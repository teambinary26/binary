<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Document revision required</title>
</head>
<body style="font-family: Arial, sans-serif; color: #17202A; line-height: 1.5;">
    <p>Good day{{ $application->applicant?->full_name ? ', '.$application->applicant->full_name : '' }}.</p>
    <p>The office reviewed application <strong>{{ $application->application_no }}</strong> for <strong>{{ $application->program?->name }}</strong> and asked you to replace this document:</p>
    <p style="font-size: 18px; font-weight: bold; color: #002D62;">{{ $document->requirement_name }}</p>
    <p><strong>Staff remarks:</strong> {{ $remarks }}</p>
    <p>Please sign in to the applicant portal and upload a clearer or corrected file for that requirement. After the replacement is uploaded, the application returns to verification.</p>
    <p><a href="{{ url('/applicant/applications/'.$application->id.'/documents') }}" style="display: inline-block; background: #002D62; color: #ffffff; padding: 10px 16px; text-decoration: none; font-weight: bold;">Replace the document</a></p>
    <p style="color: #64748B; font-size: 12px;">Local Youth Development Office of Nabua · Municipality of Nabua, Camarines Sur</p>
</body>
</html>
