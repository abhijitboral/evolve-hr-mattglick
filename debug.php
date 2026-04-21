<?php
declare(strict_types=1);
ini_set('display_errors', '0');

define('BASE_PATH', __DIR__);
require BASE_PATH . '/config/Config.php';
Config::load(BASE_PATH . '/.env');

spl_autoload_register(function (string $class): void {
    foreach ([BASE_PATH . '/services/', BASE_PATH . '/config/'] as $dir) {
        $f = $dir . $class . '.php';
        if (file_exists($f)) { require_once $f; return; }
    }
});

HubSpotService::init(Config::get('HUBSPOT_PRIVATE_APP_TOKEN', ''));

header('Content-Type: application/json');

$email = $_GET['email'] ?? '';
if (!$email) {
    echo json_encode(['error' => 'Pass ?email=you@example.com']);
    exit;
}

try {
    // Fetch contact with the password hash property
    $response = (new ReflectionMethod('HubSpotService', 'request'));
    $response->setAccessible(true);

    // Use public method
    $contact = HubSpotService::getContactByEmail($email);

    if (!$contact) {
        echo json_encode(['found' => false, 'email' => $email]);
        exit;
    }

    $props = $contact['properties'] ?? [];
    echo json_encode([
        'found'                  => true,
        'id'                     => $contact['id'],
        'email'                  => $props['email'] ?? null,
        'firstname'              => $props['firstname'] ?? null,
        'lastname'               => $props['lastname'] ?? null,
        'evolve_password_hash'   => isset($props['evolve_password_hash']) ? 'SET (length=' . strlen($props['evolve_password_hash']) . ')' : 'NOT SET',
        'all_property_keys'      => array_keys($props),
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
