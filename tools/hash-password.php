<?php
if (PHP_SAPI !== 'cli') {http_response_code(403);exit;}
fwrite(STDOUT, "Enter a strong password (input visible; do not use shared terminals): ");
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password)<12) {fwrite(STDERR, "Password must contain at least 12 characters.\n");exit(1);}
echo "PASSWORD_HASH=".password_hash($password,PASSWORD_DEFAULT).PHP_EOL;
