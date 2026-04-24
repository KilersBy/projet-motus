<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = getenv('DB_PATH') ?: __DIR__ . '/../data/motus.sqlite';
    if (!file_exists($path)) require __DIR__ . '/../db/migrate.php';
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
