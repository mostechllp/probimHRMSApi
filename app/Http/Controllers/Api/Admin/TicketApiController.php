<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TicketApiController extends ApiController
{
    /**
     * List all tickets, with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['user', 'module']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $tickets = $query->latest()->paginate($request->get('per_page', 15));

        return $this->success($tickets, 'Tickets fetched successfully.');
    }

    /**
     * Ticket dashboard stats: totals by status and by priority.
     */
    public function stats(): JsonResponse
    {
        $tickets = Ticket::select('status', 'priority')->get();

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
     * Show a single ticket.
     */
    public function show($id): JsonResponse
    {
        $ticket = Ticket::with(['user', 'module'])->find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        return $this->success($ticket, 'Ticket fetched successfully.');
    }

    /**
     * Edit a ticket's details.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $request->validate([
            'module_id' => 'nullable|integer|exists:modules,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:open,inprogress,closed,reopen',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'screenshot' => 'nullable|file|mimes:jpg,jpeg,png,gif|max:5120',
        ]);

        $data = $request->only(['module_id', 'title', 'description', 'status', 'priority']);

        if ($request->hasFile('screenshot')) {
            $data['screenshot'] = $request->file('screenshot')->store('tickets/screenshots', 'public');
        }

        $ticket->update($data);

        return $this->success($ticket->fresh(['user', 'module']), 'Ticket updated successfully.');
    }

    /**
     * Update the status of a ticket.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:open,inprogress,closed,reopen',
            'notes' => 'nullable'
        ]);

        $ticket = Ticket::find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $ticket->update(['status' => $request->status,'notes' => $request->notes]);

        return $this->success($ticket->fresh(['user', 'module']), 'Ticket status updated successfully.');
    }

    /**
     * Delete a ticket.
     */
    public function destroy($id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return $this->error('Ticket not found.', 404);
        }

        $ticket->delete();

        return $this->success(null, 'Ticket deleted successfully.');
    }
}
