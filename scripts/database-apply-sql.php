<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/database.php';

$input = $argv[1] ?? '';
if ($input === '') {
    fwrite(STDERR, "Usage: php scripts/database-apply-sql.php path/to/file.sql\n");
    exit(2);
}

$path = realpath($input);
$databaseRoot = realpath($root . '/database');
if (
    $path === false
    || $databaseRoot === false
    || !str_starts_with($path, $databaseRoot . DIRECTORY_SEPARATOR)
    || !str_ends_with(strtolower($path), '.sql')
) {
    fwrite(STDERR, "SQL file must be inside the database directory.\n");
    exit(2);
}

$sql = (string)file_get_contents($path);
$conn = getConnection();
if (!$conn->multi_query($sql)) {
    throw new RuntimeException($conn->error);
}

do {
    $result = $conn->store_result();
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    if (!$conn->more_results()) {
        break;
    }
} while ($conn->next_result());

if ($conn->errno !== 0) {
    throw new RuntimeException($conn->error);
}

$conn->close();
echo "Applied {$path}\n";
