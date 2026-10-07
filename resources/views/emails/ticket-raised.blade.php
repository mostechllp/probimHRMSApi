<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Ticket Raised</title>
</head>
<body>

    <h2>New Ticket Raised</h2>

    <p>A new ticket has been raised by an employee.</p>

    <p>
        <strong>Employee:</strong> {{ $ticket->name }}
    </p>

    <p>
        <strong>Email:</strong> {{ $ticket->email }}
    </p>

    <p>
        <strong>Ticket Title:</strong> {{ $ticket->title }}
    </p>

    <p>
        <strong>Description:</strong> {{ $ticket->description }}
    </p>

    <p>
        <strong>Priority:</strong> {{ ucfirst($ticket->priority) }}
    </p>

    <p>
        <strong>Status:</strong> {{ ucfirst($ticket->status) }}
    </p>

    <p>
        <strong>Module:</strong> {{ $ticket->module->name ?? 'N/A' }}
    </p>

    @if($ticket->screenshot)
        <p>
            <strong>Screenshot:</strong>
            <a href="{{ asset('storage/' . $ticket->screenshot) }}">
                View Screenshot
            </a>
        </p>
    @endif

</body>
</html>