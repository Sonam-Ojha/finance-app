<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Support\AdminActivity;
use App\Support\SupportTickets;
use Illuminate\Http\Request;

/**
 * Admin › Support: what users asked from Help & support (website or app),
 * and the replies. Shows who asked (name and email) and what they wrote —
 * never anything they recorded.
 */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), SupportTicket::STATUSES) ? $request->query('status') : null;
        $search = trim((string) $request->query('q', ''));

        $tickets = SupportTicket::query()
            ->with('user:id,name,email,avatar')
            ->withCount('messages')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('subject', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))))
            // Waiting for support first, the longest-waiting at the top.
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN status = 'open' THEN updated_at END ASC")
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $counts = SupportTicket::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('admin.support.index', [
            'tickets' => $tickets,
            'status'  => $status,
            'search'  => $search,
            'counts'  => $counts,
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user:id,name,email,avatar,created_at', 'messages.author:id,name']);

        return view('admin.support.show', [
            'ticket'  => $ticket,
            'history' => $ticket->user
                ? SupportTicket::where('user_id', $ticket->user_id)->whereKeyNot($ticket->id)->latest('updated_at')->limit(5)->get(['id', 'subject', 'status', 'updated_at'])
                : collect(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $request->validate(['message' => 'required|string|max:5000'], [
            'message.required' => 'Write your reply.',
            'message.max'      => 'Keep the reply to 5,000 characters.',
        ]);

        SupportTickets::reply($ticket, $request->user(), $request->input('message'));
        AdminActivity::record('support.replied', "replied to support request #{$ticket->id} from user #{$ticket->user_id}", $ticket);

        return redirect()->route('admin.support.show', $ticket)
            ->with('success', 'Reply sent. They get it by email and on their phone, and it is in Help & support.');
    }

    public function close(SupportTicket $ticket)
    {
        SupportTickets::close($ticket);
        AdminActivity::record('support.closed', "closed support request #{$ticket->id}", $ticket);

        return redirect()->route('admin.support.show', $ticket)->with('success', 'Closed. If they write again, it opens again.');
    }

    public function reopen(SupportTicket $ticket)
    {
        SupportTickets::reopen($ticket);
        AdminActivity::record('support.reopened', "reopened support request #{$ticket->id}", $ticket);

        return redirect()->route('admin.support.show', $ticket)->with('success', 'Reopened.');
    }

    public function screenshot(SupportTicket $ticket, SupportMessage $message)
    {
        abort_unless($message->ticket_id === $ticket->id, 404);

        return SupportTickets::screenshotResponse($message);
    }
}
