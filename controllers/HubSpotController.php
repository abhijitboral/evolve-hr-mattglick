<?php

declare(strict_types=1);

class HubSpotController
{
    /**
     * GET /api/hubspot/health
     */
    public static function health(Request $req, Response $res): void
    {
        try {
            HubSpotService::getContactByEmail('test@example.com');
            $res->json([
                'status'  => 'connected',
                'hubspot' => 'reachable',
                'message' => 'HubSpot API is properly configured',
            ]);
        } catch (RuntimeException $e) {
            $res->status(500)->json([
                'status'  => 'error',
                'hubspot' => 'unreachable',
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/hubspot/test-contact   (dev only)
     */
    public static function testContact(Request $req, Response $res): void
    {
        if (Config::isProduction()) {
            $res->status(403)->json(['error' => 'Not available in production']);
            return;
        }

        try {
            $ts      = time();
            $contact = HubSpotService::createContact(
                "test-{$ts}@example.com",
                'Test',
                'User',
                '+1-555-0123',
                'Test Company'
            );

            $res->json([
                'success' => true,
                'contact' => [
                    'id'    => $contact['id'],
                    'email' => $contact['properties']['email'] ?? '',
                    'name'  => trim(($contact['properties']['firstname'] ?? '') . ' ' . ($contact['properties']['lastname'] ?? '')),
                ],
            ]);
        } catch (RuntimeException $e) {
            $res->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/hubspot/contacts   (dev only)
     */
    public static function listContacts(Request $req, Response $res): void
    {
        if (Config::isProduction()) {
            $res->status(403)->json(['error' => 'Not available in production']);
            return;
        }

        $apiKey = Config::get('HUBSPOT_PRIVATE_APP_TOKEN', '');
        $url    = 'https://api.hubapi.com/crm/v3/objects/contacts?limit=100&properties=email,firstname,lastname';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);
        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        if ($httpCode >= 400) {
            $res->status(500)->json(['error' => "HubSpot error $httpCode"]);
            return;
        }

        $data     = json_decode($raw ?: '{}', true);
        $contacts = array_map(function ($c) {
            return [
                'id'        => $c['id'],
                'email'     => $c['properties']['email'] ?? '',
                'firstName' => $c['properties']['firstname'] ?? '',
                'lastName'  => $c['properties']['lastname'] ?? '',
            ];
        }, $data['results'] ?? []);

        $res->json(['success' => true, 'total' => count($contacts), 'contacts' => $contacts]);
    }

    /**
     * DELETE /api/hubspot/contacts/:id   (dev only)
     */
    public static function deleteContact(Request $req, Response $res): void
    {
        if (Config::isProduction()) {
            $res->status(403)->json(['error' => 'Not available in production']);
            return;
        }

        $id     = $req->param('id');
        $apiKey = Config::get('HUBSPOT_PRIVATE_APP_TOKEN', '');
        $url    = "https://api.hubapi.com/crm/v3/objects/contacts/{$id}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);


        if ($httpCode >= 400) {
            $res->status(500)->json(['error' => "Failed to delete contact. HTTP $httpCode"]);
            return;
        }

        $res->json(['success' => true, 'message' => "Contact {$id} deleted"]);
    }
}
