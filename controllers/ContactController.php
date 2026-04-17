<?php

declare(strict_types=1);

class ContactController
{
    /**
     * POST /api/contact
     */
    public static function submit(Request $req, Response $res): void
    {
        $name    = trim((string) $req->body('name', ''));
        $email   = strtolower(trim((string) $req->body('email', '')));
        $phone   = trim((string) $req->body('phone', ''));
        $company = trim((string) $req->body('company', ''));
        $subject = trim((string) $req->body('subject', ''));
        $message = trim((string) $req->body('message', ''));

        if (!$name || !$email || !$message) {
            $res->status(400)->json(['error' => 'Name, email, and message required']);
        }

        try {
            $contact = HubSpotService::getContactByEmail($email);

            if (!$contact) {
                $parts     = explode(' ', $name, 2);
                $firstName = $parts[0];
                $lastName  = $parts[1] ?? 'Contact';
                $contact   = HubSpotService::createContact($email, $firstName, $lastName, $phone, $company);
            } else {
                $properties = [];
                if ($phone)   $properties['phone']   = $phone;
                if ($company) $properties['company'] = $company;
                if ($properties) {
                    HubSpotService::updateContact($contact['id'], $properties);
                }
            }

            $ticketSubject = $subject ?: "New Inquiry from $name";
            $ticketBody    = "Name: $name\nEmail: $email\nPhone: " . ($phone ?: 'N/A') .
                             "\nCompany: " . ($company ?: 'N/A') . "\n\nMessage:\n$message";

            $ticket = HubSpotService::createTicket($ticketSubject, $ticketBody, $contact['id'], 'MEDIUM');

            $res->status(201)->json([
                'success'   => true,
                'message'   => 'Thank you! Your inquiry has been received. We will get back to you shortly.',
                'ticketId'  => $ticket['id'],
                'contactId' => $contact['id'],
            ]);
        } catch (RuntimeException $e) {
            $res->status(500)->json(['error' => 'Failed to process your inquiry']);
        }
    }
}
