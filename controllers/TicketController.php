<?php

declare(strict_types=1);

class TicketController
{
    private static array $statusMap = [
        '1'                => 'new',
        '2'                => 'waiting_customer',
        '3'                => 'in_progress',
        '4'                => 'closed',
        'new'              => 'new',
        'in_progress'      => 'in_progress',
        'waiting_customer' => 'waiting_customer',
        'closed'           => 'closed',
    ];

    private static function normaliseStatus($stage): string
    {
        return self::$statusMap[strtolower((string) $stage)] ?? 'new';
    }

    private static function formatTicket(array $ticket): array
    {
        $props   = $ticket['properties'] ?? [];
        $created = $props['createdate'] ? date('n/j/Y', strtotime($props['createdate'])) : 'N/A';
        $updated = $props['notes_last_updated'] ? date('n/j/Y', strtotime($props['notes_last_updated'])) : 'N/A';

        return [
            'id'          => $ticket['id'],
            'ticketId'    => $props['hs_ticket_id'] ?? null,
            'subject'     => $props['subject'] ?? '',
            'description' => $props['content'] ?? '',
            'status'      => self::normaliseStatus($props['hs_pipeline_stage'] ?? ''),
            'priority'    => $props['hs_ticket_priority'] ?? 'MEDIUM',
            'created'     => $created,
            'updated'     => $updated,
        ];
    }

    /**
     * GET /api/tickets/debug/all
     */
    public static function debugAll(Request $req, Response $res): void
    {
        try {
            $tickets = HubSpotService::getAllTickets();
            $res->json([
                'success'    => true,
                'debug'      => true,
                'totalCount' => count($tickets),
                'tickets'    => array_map(fn($t) => [
                    'id'      => $t['id'],
                    'subject' => $t['properties']['subject'] ?? '',
                    'created' => $t['properties']['createdate'] ?? '',
                ], $tickets),
            ]);
        } catch (RuntimeException $e) {
            $res->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/tickets
     */
    public static function index(Request $req, Response $res): void
    {
        $contactId = $req->user['contactId'];

        try {
            $tickets          = HubSpotService::getTicketsByContact($contactId);
            $formattedTickets = array_map([self::class, 'formatTicket'], $tickets);

            $res->json([
                'success' => true,
                'count'   => count($formattedTickets),
                'tickets' => $formattedTickets,
            ]);
        } catch (RuntimeException $e) {
            $res->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/tickets/:id
     */
    public static function show(Request $req, Response $res): void
    {
        $ticketId = $req->param('id');

        try {
            $ticket = HubSpotService::getTicketById($ticketId);
            $res->json(['success' => true, 'ticket' => self::formatTicket($ticket)]);
        } catch (RuntimeException $e) {
            $res->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/tickets
     */
    public static function store(Request $req, Response $res): void
    {
        $subject     = trim((string) $req->body('subject', ''));
        $description = trim((string) $req->body('description', ''));
        $priority    = strtoupper((string) $req->body('priority', 'MEDIUM'));
        $contactId   = $req->user['contactId'];

        if (!$subject || !$description) {
            $res->status(400)->json(['error' => 'Subject and description required']);
        }

        try {
            $newTicket = HubSpotService::createTicket($subject, $description, $contactId, $priority);
            $props     = $newTicket['properties'] ?? [];

            $res->status(201)->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'ticket'  => [
                    'id'          => $newTicket['id'],
                    'ticketId'    => $props['hs_ticket_id'] ?? null,
                    'subject'     => $props['subject'] ?? $subject,
                    'description' => $props['content']  ?? $description,
                    'status'      => 'new',
                    'priority'    => $priority,
                    'created'     => date('n/j/Y'),
                ],
            ]);
        } catch (RuntimeException $e) {
            $res->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * PATCH /api/tickets/:id/status
     */
    public static function updateStatus(Request $req, Response $res): void
    {
        $ticketId = $req->param('id');
        $status   = trim((string) $req->body('status', ''));

        if (!$status) {
            $res->status(400)->json(['error' => 'Status required']);
        }

        try {
            $updated = HubSpotService::updateTicketStatus($ticketId, $status);
            $res->json([
                'success'  => true,
                'message'  => 'Ticket status updated',
                'ticketId' => $updated['id'],
            ]);
        } catch (RuntimeException $e) {
            $res->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/tickets/:id/notes
     */
    public static function addNote(Request $req, Response $res): void
    {
        $ticketId = $req->param('id');
        $note     = trim((string) $req->body('note', ''));

        if (!$note) {
            $res->status(400)->json(['error' => 'Note required']);
        }

        try {
            HubSpotService::addNoteToTicket($ticketId, $note);
            $res->status(201)->json(['success' => true, 'message' => 'Note added successfully']);
        } catch (RuntimeException $e) {
            $res->status(500)->json(['error' => $e->getMessage()]);
        }
    }
}
