<?php
// Debug page removed. This file was used for temporary diagnostics and has been cleaned.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error' => 'Debug endpoint removed'], JSON_PRETTY_PRINT);
