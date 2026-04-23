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

$action = $_GET['action'] ?? 'pipelines';

if ($action === 'pipelines') {
    // Fetch all ticket pipelines and their stages
    $ch = curl_init('https://api.hubapi.com/crm/v3/pipelines/tickets');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . Config::get('HUBSPOT_PRIVATE_APP_TOKEN', ''),
            'Content-Type: application/json',
        ],
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($raw, true);
    $out  = [];
    foreach ($data['results'] ?? [] as $pipeline) {
        $stages = [];
        foreach ($pipeline['stages'] ?? [] as $stage) {
            $stages[] = ['id' => $stage['id'], 'label' => $stage['label']];
        }
        $out[] = [
            'pipeline_id'    => $pipeline['id'],
            'pipeline_label' => $pipeline['label'],
            'stages'         => $stages,
        ];
    }
    echo json_encode(['http_code' => $code, 'pipelines' => $out], JSON_PRETTY_PRINT);

} elseif ($action === 'contact' && isset($_GET['email'])) {
    try {
        $contact = HubSpotService::getContactByEmail($_GET['email']);
        echo json_encode([
            'found'                => (bool) $contact,
            'id'                   => $contact['id'] ?? null,
            'evolve_password_hash' => isset($contact['properties']['evolve_password_hash'])
                ? 'SET (len=' . strlen($contact['properties']['evolve_password_hash']) . ')'
                : 'NOT SET',
            'properties'           => array_keys($contact['properties'] ?? []),
        ], JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
