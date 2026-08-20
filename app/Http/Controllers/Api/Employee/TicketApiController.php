<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use App\Models\Module;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TicketApiController extends ApiController
{
    /**
     * List the active modules/pages for the ticket form dropdown.
     */
    public function modules(): JsonResponse
    {
        $modules = Module::where('status', 'active')->get(['id', 'name', 'slug']);

        return $this->success($modules, 'Modules fetched successfully.');
    }

    /**
     * Stats for the authenticated employee's own tickets: totals by status
     * and by priority.
     */
    public function stats(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $tickets = Ticket::where('user_id', $user->id)->get(['status', 'priority']);

        return $this->success([
            'total_tickets' => $tickets->count(),
            'by_status' => [
                'open' => $tickets->where('status', 'open')->count(),
                'inprogress' => $tickets->where('status', 'inprogress')->count(),
                'closed' => $tickets->where('status', 'closed')->count(),
                'reopen' => $tickets->where('status', 'reopen')->count(),
            ],
            'by_priority' => [
                'low' => $tickets->where('priority', 'low')->count(),
                'medium' => $tickets->where('priority', 'medium')->count(),
                'high' => $tickets->where('priority', 'high')->count(),
                'urgent' => $tickets->where('priority', 'urgent')->count(),
            ],
        ], 'Ticket statistics fetched successfully.');
    }

    /**
     * List the authenticated employee's own tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $query = Ticket::with('module')->where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $tickets = $query->latest()->paginate($request->get('per_page', 15));

        return $this->success($tickets, 'Tickets fetched successfully.');
    }

    /**
     * Raise a new ticket. Name and email are taken from the authenticated
     * user, not from client input.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $request->validate([
            'module_id' => 'required|integer|exists:modules,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'screenshot' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:5120',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $employee = $user->employee;
        $name = $employee
            ? trim($employee->first_name . ' ' . $employee->last_name)
            : $user->username;

        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request->file('screenshot')->store('tickets/screenshots', 'public');
        }

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'name' => $name,
            'email' => $user->email,
            'module_id' => $request->module_id,
            'title' => $request->title,
            'description' => $request->description,
            'screenshot' => $screenshotPath,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open',
        ]);

        return $this->success($ticket->load('module'), 'Ticket raised successfully.', 201);
    }

    /**
     * Show a single ticket, scoped to the authenticated employee.
     */
    public function show($id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $ticket = Ticket::with('module')->where('user_id', $user->id)->find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        return $this->success($ticket, 'Ticket fetched successfully.');
    }

    /**
     * Edit one of my own tickets. Only allowed while the ticket is still
     * "open" — once an admin has moved it to inprogress/closed/reopen it
     * can no longer be edited here.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $ticket = Ticket::where('user_id', $user->id)->find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        if ($ticket->status !== 'open') {
            return $this->error('This ticket can no longer be edited.', 422);
        }

        $request->validate([
            'module_id' => 'nullable|integer|exists:modules,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'screenshot' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:5120',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $data = $request->only(['module_id', 'title', 'description', 'priority']);

        if ($request->hasFile('screenshot')) {
            $data['screenshot'] = $request->file('screenshot')->store('tickets/screenshots', 'public');
        }

        $ticket->update($data);

        return $this->success($ticket->fresh('module'), 'Ticket updated successfully.');
    }

    /**
     * Delete one of my own tickets. Only allowed while the ticket is still
     * "open".
     */
    public function destroy($id): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $ticket = Ticket::where('user_id', $user->id)->find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        if ($ticket->status !== 'open') {
            return $this->error('This ticket can no longer be deleted.', 422);
        }

        $ticket->delete();

        return $this->success(null, 'Ticket deleted successfully.');
    }
}
