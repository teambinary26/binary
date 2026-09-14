<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Release scheduled</title>
</head>
<body style="font-family: Arial, sans-serif; color: #17202A; line-height: 1.5;">
    <p>Good day{{ $application->applicant?->full_name ? ', '.$application->applicant->full_name : '' }}.</p>
    <p>Your application <strong>{{ $application->application_no }}</strong> for <strong>{{ $application->program?->name }}</strong>
        @if($rescheduled)
            has been <strong>rescheduled</strong> for release.
        @else
            has been scheduled for release.
        @endif
    </p>
    <p>
        Release date: <strong>{{ gov_date($schedule->release_date) }}</strong><br>
        Location: <strong>{{ $schedule->release_location }}</strong><br>
        Method: <strong>{{ $schedule->methodLabel() }}</strong>
        @if($application->approved_amount)
            <br>Approved amount: <strong>{{ peso($application->approved_amount) }}</strong>
        @endif
    </p>
    @if($schedule->notes)
        <p><strong>Notes:</strong> {{ $schedule->notes }}</p>
    @endif
    <p>Please bring one valid ID and arrive on the scheduled date. You may also sign in to the applicant portal to view this schedule.</p>
    <p><a href="{{ url('/applicant/applications/'.$application->id) }}" style="display: inline-block; background: #002D62; color: #ffffff; padding: 10px 16px; text-decoration: none; font-weight: bold;">View application</a></p>
    <p style="color: #64748B; font-size: 12px;">Local Youth Development Office of Nabua · Municipality of Nabua, Camarines Sur</p>
</body>
</html>
