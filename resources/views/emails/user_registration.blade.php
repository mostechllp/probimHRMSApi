<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Mostech HRMS</title>
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
            background-color: #4f46e5;
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
        .user-info {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .user-info p {
            margin: 10px 0;
        }
        .label {
            font-weight: bold;
            color: #6b7280;
            width: 120px;
            display: inline-block;
        }
        .value {
            color: #111827;
            font-family: monospace;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            background-color: #4f46e5;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin-top: 20px;
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
            <h1>Welcome to Probim HRMS</h1>
        </div>
        <div class="content">
            <p>Hello {{ $employee->first_name }},</p>
            <p>Your account has been successfully created in the Probim HRMS system. You can now log in using the credentials below:</p>
            
            <div class="user-info">
                <p><span class="label">Username:</span> <span class="value">{{ $user->username }}</span></p>
                <p><span class="label">Password:</span> <span class="value">{{ $password }}</span></p>
            </div>
            
            <p>For security reasons, we recommend that you change your password after your first login.</p>
            
            <a href="https://probim.vercel.app" class="button">Go to Dashboard</a>
            
            <p>If you have any questions, please contact our HR department.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Mostech HRMS. All rights reserved.
        </div>
    </div>
</body>
</html>
