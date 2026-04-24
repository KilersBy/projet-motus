<?php
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare('SELECT id, pseudo AS username FROM "Users" WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function require_user(): array {
    $user = current_user();
    if (!$user) json_response(['error' => 'Connexion requise'], 401);
    return $user;
}
