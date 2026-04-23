<?php

declare(strict_types=1);

class HubSpotService
{
    private static string $apiKey  = '';
    private static string $baseUrl = 'https://api.hubapi.com';

    public static function init(string $apiKey): void
    {
        self::$apiKey = $apiKey;
    }

    // -------------------------------------------------------------------------
    // Contacts
    // -------------------------------------------------------------------------

    public static function getContactByEmail(string $email): ?array
    {
        $email = strtolower($email);
        $response = self::request('POST', '/crm/v3/objects/contacts/search', [
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => 'email',
                    'operator'     => 'EQ',
                    'value'        => $email,
                ]]
            ]],
            'limit'      => 1,
            'properties' => ['email', 'firstname', 'lastname', 'phone', 'company', 'evolve_password_hash'],
        ]);

        if ($response === null) {
            throw new RuntimeException('Failed to fetch contact');
        }

        $results = $response['results'] ?? [];
        return count($results) > 0 ? $results[0] : null;
    }

    public static function getContactById(string $contactId): array
    {
        $response = self::request('GET', "/crm/v3/objects/contacts/{$contactId}", null, [
            'properties' => 'email,firstname,lastname,phone,company',
        ]);

        if ($response === null) {
            throw new RuntimeException('Failed to fetch contact');
        }

        return $response;
    }

    public static function createContact(
        string $email,
        string $firstName,
        string $lastName,
        string $phone = '',
        string $company = '',
        string $passwordHash = ''
    ): array {
        $email    = strtolower($email);
        $props    = [
            'email'     => $email,
            'firstname' => $firstName,
            'lastname'  => $lastName,
            'phone'     => $phone,
            'company'   => $company,
        ];
        if ($passwordHash !== '') {
            $props['evolve_password_hash'] = $passwordHash;
        }
        $response = self::request('POST', '/crm/v3/objects/contacts', [
            'properties' => $props,
        ]);

        if ($response === null) {
            throw new RuntimeException('Failed to create contact');
        }

        return $response;
    }

    public static function updateContact(string $contactId, array $properties): array
    {
        $response = self::request('PATCH', "/crm/v3/objects/contacts/{$contactId}", [
            'properties' => $properties,
        ]);

        if ($response === null) {
            throw new RuntimeException('Failed to update contact');
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Tickets
    // -------------------------------------------------------------------------

    public static function getTicketsByContact(string $contactId): array
    {
        $ticketProperties = [
            'hs_ticket_id', 'subject', 'content', 'hs_pipeline_stage',
            'hs_ticket_priority', 'createdate', 'notes_last_updated', 'contact_id_link',
        ];

        // Primary: v3 associations
        try {
            $assocResponse = self::request('GET', "/crm/v3/objects/contacts/{$contactId}/associations/tickets");
            $associations  = $assocResponse['results'] ?? [];
            if (count($associations) > 0) {
                $ids           = array_map(fn($a) => ['id' => (string) $a['id']], $associations);
                $batchResponse = self::request('POST', '/crm/v3/objects/tickets/batch/read', [
                    'inputs'     => $ids,
                    'properties' => $ticketProperties,
                ]);
                return $batchResponse['results'] ?? [];
            }
        } catch (RuntimeException $e) {
            // Contact has no associations yet or HubSpot returned an error — treat as empty
        }

        // Fallback: contact_id_link search
        try {
            $searchResponse = self::request('POST', '/crm/v3/objects/tickets/search', [
                'filterGroups' => [[
                    'filters' => [[
                        'propertyName' => 'contact_id_link',
                        'operator'     => 'EQ',
                        'value'        => $contactId,
                    ]]
                ]],
                'limit'      => 100,
                'properties' => $ticketProperties,
            ]);
            return $searchResponse['results'] ?? [];
        } catch (RuntimeException $e) {
            // No tickets found via search either
        }

        return [];
    }

    public static function getTicketById(string $ticketId): array
    {
        $response = self::request('GET', "/crm/v3/objects/tickets/{$ticketId}", null, [
            'properties' => 'hs_ticket_id,subject,content,hs_pipeline_stage,hs_ticket_priority,createdate,notes_last_updated',
        ]);

        if ($response === null) {
            throw new RuntimeException('Failed to fetch ticket');
        }

        return $response;
    }

    public static function getAllTickets(): array
    {
        $response = self::request('GET', '/crm/v3/objects/tickets', null, [
            'limit'      => 100,
            'properties' => 'hs_ticket_id,subject,content,hs_pipeline_stage,hs_ticket_priority,createdate,notes_last_updated',
        ]);

        if ($response === null) {
            throw new RuntimeException('Failed to fetch tickets');
        }

        return $response['results'] ?? [];
    }

    public static function createTicket(
        string $subject,
        string $description,
        string $contactId,
        string $priority = 'MEDIUM'
    ): array {
        $ticketResponse = self::request('POST', '/crm/v3/objects/tickets', [
            'properties' => [
                'subject'             => $subject,
                'content'             => $description,
                'hs_ticket_priority'  => strtoupper($priority),
                'hs_pipeline_stage'   => '1',
            ],
        ]);

        if ($ticketResponse === null) {
            throw new RuntimeException('Failed to create ticket');
        }

        $ticketId = $ticketResponse['id'];

        // Associate ticket ↔ contact
        self::request(
            'PUT',
            "/crm/v3/objects/tickets/{$ticketId}/associations/contacts/{$contactId}/ticket_to_contact"
        );

        return $ticketResponse;
    }

    public static function createContactFormTicket(
        string $name,
        string $email,
        string $phone,
        string $company,
        string $subject,
        string $message,
        string $contactId
    ): array {
        $ticketSubject = $subject ?: "Inquiry from $name";

        $content = implode("\n", [
            '--- NEW CONTACT INQUIRY ---',
            '',
            'Name    : ' . $name,
            'Email   : ' . $email,
            'Phone   : ' . ($phone   ?: 'Not provided'),
            'Company : ' . ($company ?: 'Not provided'),
            '',
            'Subject : ' . ($subject ?: 'General Inquiry'),
            '',
            'Message :',
            $message,
            '',
            '--- Submitted via evolvehrteam.com ---',
        ]);

        $ticketResponse = self::request('POST', '/crm/v3/objects/tickets', [
            'properties' => [
                'subject'             => $ticketSubject,
                'content'             => $content,
                'hs_ticket_priority'  => 'HIGH',
                'hs_pipeline_stage'   => '1',
                'hs_ticket_category'  => 'NEW CONTACT',
            ],
        ]);

        if ($ticketResponse === null) {
            throw new RuntimeException('Failed to create contact inquiry ticket');
        }

        $ticketId = $ticketResponse['id'];

        // Associate ticket ↔ contact
        self::request(
            'PUT',
            "/crm/v3/objects/tickets/{$ticketId}/associations/contacts/{$contactId}/ticket_to_contact"
        );

        return $ticketResponse;
    }

    public static function updateTicketStatus(string $ticketId, string $status): array
    {
        $response = self::request('PATCH', "/crm/v3/objects/tickets/{$ticketId}", [
            'properties' => ['hs_pipeline_stage' => $status],
        ]);

        if ($response === null) {
            throw new RuntimeException('Failed to update ticket');
        }

        return $response;
    }

    public static function addNoteToTicket(string $ticketId, string $note): array
    {
        $response = self::request(
            'POST',
            "/crm/v3/objects/tickets/{$ticketId}/associations/notes",
            ['properties' => ['hs_note_body' => $note]]
        );

        if ($response === null) {
            throw new RuntimeException('Failed to add note');
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // HTTP helper — cURL
    // -------------------------------------------------------------------------

    private static function request(
        string  $method,
        string  $path,
        ?array  $body   = null,
        array   $query  = []
    ): ?array {
        $url = self::$baseUrl . $path;
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . self::$apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);

        if ($curlErr) {
            error_log("HubSpot cURL error on $method $path: $curlErr");
            return null;
        }

        $data = json_decode($raw ?: '{}', true);

        if ($httpCode >= 400) {
            $msg = $data['message'] ?? $raw;
            error_log("HubSpot API error $httpCode on $method $path: $msg");
            throw new RuntimeException($msg ?: "HubSpot API error $httpCode");
        }

        return $data;
    }
}
