<?php

namespace App\Http\Controllers\Tracker;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Support\SupportTickets;
use Illuminate\Http\Request;

/**
 * My account › Help & support: ask a question (with a screenshot if it
 * helps), see past requests and the replies, and write back. The same
 * tickets as the app's (/api/v1/support) and Admin › Support.
 */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        return view('tracker.support', [
            'tickets' => SupportTicket::where('user_id', $request->user()->id)
                ->withCount('messages')
                ->latest('updated_at')->latest('id')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(SupportTickets::rules() + [
            'screenshot' => 'nullable|file|max:5120',
        ], SupportTickets::messages() + [
            'screenshot.*' => SupportTickets::SCREENSHOT_MESSAGE,
        ]);

        $screenshot = $request->hasFile('screenshot') ? SupportTickets::fromUpload($request->file('screenshot')) : null;
        $ticket = SupportTickets::open($request->user(), $request->input('subject'), $request->input('message'), $screenshot);

        return redirect()->route('support.show', $ticket)
            ->with('success', 'Request sent. We reply here and by email, usually within a day.');
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        $this->authorizeOwner($request, $ticket);
        SupportTickets::markRead($ticket);
        $ticket->load('messages');

        return view('tracker.support-ticket', ['ticket' => $ticket]);
    }

    public function message(Request $request, SupportTicket $ticket)
    {
        $this->authorizeOwner($request, $ticket);
        $request->validate(['message' => 'required|string|max:5000'], SupportTickets::messages());

        SupportTickets::addUserMessage($ticket, $request->user(), $request->input('message'));

        return redirect()->route('support.show', $ticket)->with('success', 'Sent. We will get back to you here and by email.');
    }

    public function screenshot(Request $request, SupportTicket $ticket, SupportMessage $message)
    {
        $this->authorizeOwner($request, $ticket);
        abort_unless($message->ticket_id === $ticket->id, 404);

        return SupportTickets::screenshotResponse($message);
    }

    private function authorizeOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
    }
}
