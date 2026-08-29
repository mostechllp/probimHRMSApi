<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punch-In Reminder</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #b45309;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 40px;
        }
        .info-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-box p {
            margin: 10px 0;
        }
        .label {
            font-weight: bold;
            color: #6b7280;
            width: 160px;
            display: inline-block;
        }
        .value {
            color: #111827;
        }
        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Punch-In Reminder</h1>
        </div>
        <div class="content">
            <p>Hello {{ $employee->first_name }},</p>
            <p>Our records show that you have not punched in yet today, <strong>{{ $date }}</strong>.</p>

            <div class="info-box">
                <p><span class="label">Scheduled Start:</span> <span class="value">{{ $scheduledStart }}</span></p>
            </div>

            <p>If you are working today, please punch in as soon as possible. If you are on approved leave or this is not applicable, you can disregard this reminder.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Mostech HRMS. All rights reserved.
        </div>
    </div>
</body>
</html>
