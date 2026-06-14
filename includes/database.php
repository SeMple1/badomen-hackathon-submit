<?php
declare(strict_types=1);

final class DatabaseConnectionException extends RuntimeException
{
}

function getConnection(): mysqli
{
    $hostname = trim((string)getenv('DB_HOST'));
    $dbName = trim((string)getenv('DB_NAME'));
    $username = trim((string)getenv('DB_USER'));
    $password = (string)getenv('DB_PASSWORD');
    $port = (int)(getenv('DB_PORT') ?: 3306);

    if ($hostname === '' || $dbName === '' || $username === '') {
        throw new DatabaseConnectionException('Database environment variables are not configured.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = mysqli_init();
    if ($conn === false) {
        throw new DatabaseConnectionException('Unable to initialize the database client.');
    }

    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, max(1, (int)(getenv('DB_CONNECT_TIMEOUT') ?: 5)));

    try {
        $conn->real_connect($hostname, $username, $password, $dbName, $port);
    } catch (mysqli_sql_exception $error) {
        error_log('Database connection failed: ' . $error->getCode());
        throw new DatabaseConnectionException('The database is temporarily unavailable.', 0, $error);
    }

    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+07:00'");

    return $conn;
}
