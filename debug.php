<?php
header('Content-Type: application/json');
echo json_encode([
    'REQUEST_URI'     => $_SERVER['REQUEST_URI']     ?? null,
    'SCRIPT_NAME'     => $_SERVER['SCRIPT_NAME']     ?? null,
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? null,
    'DOCUMENT_ROOT'   => $_SERVER['DOCUMENT_ROOT']   ?? null,
    'PHP_SELF'        => $_SERVER['PHP_SELF']         ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
