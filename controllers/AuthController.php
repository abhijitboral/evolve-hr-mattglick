<?php

declare(strict_types=1);

class AuthController
{
    /**
     * POST /api/auth/login
     */
    public static function login(Request $req, Response $res): void
    {
        $email    = trim((string) $req->body('email', ''));
        $password = (string) $req->body('password', '');

        if (!$email || !$password) {
            $res->status(400)->json(['error' => 'Email and password required']);
        }

        $email = strtolower($email);

        try {
            $contact = HubSpotService::getContactByEmail($email);
        } catch (RuntimeException $e) {
            $res->status(503)->json(['error' => 'Service temporarily unavailable. Please try again later.']);
        }

        if (!$contact) {
            $res->status(401)->json(['error' => 'Invalid credentials']);
        }

        // In production, verify password hash stored in HubSpot custom field:
        // if (!AuthService::comparePassword($password, $contact['properties']['hs_password_hash'] ?? '')) {
        //     $res->status(401)->json(['error' => 'Invalid credentials']);
        // }

        $name  = trim(($contact['properties']['firstname'] ?? '') . ' ' . ($contact['properties']['lastname'] ?? ''));
        $user  = [
            'contactId' => $contact['id'],
            'email'     => $email,
            'name'      => $name,
        ];

        $token           = AuthService::generateToken($user);
        $_SESSION['user'] = $user;

        $res->json(['success' => true, 'token' => $token, 'user' => $user]);
    }

    /**
     * POST /api/auth/register
     */
    public static function register(Request $req, Response $res): void
    {
        $email     = strtolower(trim((string) $req->body('email', '')));
        $password  = (string) $req->body('password', '');
        $firstName = trim((string) $req->body('firstName', ''));
        $lastName  = trim((string) $req->body('lastName', ''));
        $company   = trim((string) $req->body('company', ''));
        $phone     = trim((string) $req->body('phone', ''));

        if (!$email || !$password || !$firstName || !$lastName) {
            $res->status(400)->json(['error' => 'Missing required fields']);
        }

        try {
            $existing = HubSpotService::getContactByEmail($email);
        } catch (RuntimeException $e) {
            $res->status(503)->json(['error' => 'Service temporarily unavailable. Please try again later.']);
        }

        if ($existing) {
            $res->status(409)->json(['error' => 'Email already registered']);
        }

        try {
            $newContact = HubSpotService::createContact($email, $firstName, $lastName, $phone, $company);
        } catch (RuntimeException $e) {
            $res->status(400)->json(['error' => 'Failed to create account. Please check your information and try again.']);
        }

        $user = [
            'contactId' => $newContact['id'],
            'email'     => $email,
            'name'      => "$firstName $lastName",
        ];

        $token            = AuthService::generateToken($user);
        $_SESSION['user'] = $user;

        $res->status(201)->json([
            'success'   => true,
            'token'     => $token,
            'user'      => $user,
            'contactId' => $newContact['id'],
        ]);
    }

    /**
     * GET /api/auth/profile
     */
    public static function profile(Request $req, Response $res): void
    {
        $currentUser = $req->user;

        try {
            $contact = HubSpotService::getContactByEmail($currentUser['email']);
        } catch (RuntimeException $e) {
            $res->json(['user' => $currentUser, 'authenticated' => true]);
        }

        if (!$contact) {
            $res->json(['user' => $currentUser, 'authenticated' => true]);
        }

        $name = trim(($contact['properties']['firstname'] ?? '') . ' ' . ($contact['properties']['lastname'] ?? ''))
              ?: $currentUser['name'];

        $user  = [
            'contactId' => $contact['id'],
            'email'     => $currentUser['email'],
            'name'      => $name,
        ];
        $token = AuthService::generateToken($user);

        $res->json(['user' => $user, 'token' => $token, 'authenticated' => true]);
    }

    /**
     * POST /api/auth/logout
     */
    public static function logout(Request $req, Response $res): void
    {
        session_destroy();
        $res->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    /**
     * POST /api/auth/refresh
     */
    public static function refresh(Request $req, Response $res): void
    {
        $user  = $req->user;
        $token = AuthService::generateToken([
            'contactId' => $user['contactId'],
            'email'     => $user['email'],
            'name'      => $user['name'],
        ]);

        $res->json(['token' => $token, 'user' => $user]);
    }
}
