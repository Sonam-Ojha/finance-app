<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Support\SupportTickets;
use Illuminate\Http\Request;

/**
 * The app's Help & support (App\Support\SupportTickets). Someone else's
 * ticket is a 404.
 *
 *   Ticket       = { id, subject, status: open|answered|closed, created_at, updated_at,
 *                    last_reply_from: user|support|null, unread }
 *   TicketDetail = Ticket + { messages: [{ id, from: user|support, body, created_at, has_screenshot }] }
 */
class SupportController extends Controller
{
    /** GET /support — the user's requests, the latest activity first. */
    public function index(Request $request)
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)->latest('updated_at')->latest('id')->get();

        return response()->json(['data' => $tickets->map(fn ($t) => SupportTickets::toApi($t))->values()]);
    }

    /** POST /support { subject, message, screenshot?: "data:image/…;base64,…" } → 201 TicketDetail. */
    public function store(Request $request)
    {
        $request->validate(SupportTickets::rules() + [
            'screenshot' => 'nullable|string',
        ], SupportTickets::messages() + [
            'screenshot.*' => SupportTickets::SCREENSHOT_MESSAGE,
        ]);

        $screenshot = $request->filled('screenshot') ? SupportTickets::decodeScreenshot($request->input('screenshot')) : null;
        $ticket = SupportTickets::open($request->user(), $request->input('subject'), $request->input('message'), $screenshot);

        return response()->json(['data' => SupportTickets::toApiDetail($ticket->fresh('messages'))], 201);
    }

    /** GET /support/{id} — the conversation; reading it marks the latest reply as seen. */
    public function show(Request $request, SupportTicket $ticket)
    {
        $this->authorizeOwner($request, $ticket);
        SupportTickets::markRead($ticket);

        return response()->json(['data' => SupportTickets::toApiDetail($ticket->load('messages'))]);
    }

    /** POST /support/{id}/messages { message } — opens a closed ticket again. */
    public function message(Request $request, SupportTicket $ticket)
    {
        $this->authorizeOwner($request, $ticket);
        $request->validate(['message' => 'required|string|max:5000'], SupportTickets::messages());

        SupportTickets::addUserMessage($ticket, $request->user(), $request->input('message'));

        return response()->json(['data' => SupportTickets::toApiDetail($ticket->fresh('messages'))]);
    }

    /** GET /support/{id}/messages/{messageId}/screenshot — the image itself (Bearer token). */
    public function screenshot(Request $request, SupportTicket $ticket, SupportMessage $message)
    {
        $this->authorizeOwner($request, $ticket);
        abort_unless($message->ticket_id === $ticket->id, 404, 'Not found.');

        return SupportTickets::screenshotResponse($message);
    }

    private function authorizeOwner(Request $request, SupportTicket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404, 'Not found.');
    }
}
