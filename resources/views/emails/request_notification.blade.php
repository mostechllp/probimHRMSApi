<!DOCTYPE html>
<html>
<head>
    <title>{{ $requestType }} Notification</title>
</head>
<body>
    <h2>{{ $requestType }} {{ ucfirst($action) }}</h2>
    <p>A {{ strtolower($requestType) }} has been {{ $action }}.</p>
    <p><strong>Employee:</strong> {{ $requestDetails->employee->user->username ?? $requestDetails->employee->first_name ?? 'N/A' }}</p>
    <p><strong>Status:</strong> {{ $requestDetails->status ?? 'pending' }}</p>

    <p>Please log in to the admin panel to view the details.</p>

    <br>
    <p>Thanks,<br>{{ config('app.name') }}</p>
</body>
</html>
