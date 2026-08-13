<?php

$filePath = __DIR__ . '/../Ally_backend_new/Ally_backend_new.json';

if (!file_exists($filePath)) {
    echo "File not found!\n";
    exit(1);
}

$content = file_get_contents($filePath);

// Replace passwords
$content = str_replace(
    ['StrongPassword123!', 'P@ssw0rd123', 'PasswordBaru123!', 'PasswordBaru456@', 'PasswordKuat123!', 'NewPassword123@'],
    '{{default_password}}',
    $content
);

// Replace entropy token
$content = str_replace(
    'c9a58601560d570713bd6de071054c9cc1e874954ae16c9ecb8a62887eea381c',
    '{{sample_reset_token}}',
    $content
);

file_put_contents($filePath, $content);

echo "✅ Ally_backend_new.json sanitized successfully!\n";
