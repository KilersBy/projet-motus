<?php
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Words.php';
require __DIR__ . '/../src/Game.php';

function json_body(): array { return json_decode(file_get_contents('php://input'), true) ?: []; }
function json_response($data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function request_path(): string { return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'; }

$path = request_path();

if ($path === '/swagger') { header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__.'/swagger.html'); exit; }
if ($path === '/openapi.json') { header('Content-Type: application/json; charset=utf-8'); readfile(__DIR__.'/openapi.json'); exit; }

// Servir les fichiers statiques du front-end : /style.css, /app.js, etc.
$publicRoot = realpath(__DIR__);
$requestedFile = realpath(__DIR__ . $path);
if ($path !== '/' && $requestedFile && str_starts_with($requestedFile, $publicRoot) && is_file($requestedFile)) {
    $ext = strtolower(pathinfo($requestedFile, PATHINFO_EXTENSION));
    $types = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    readfile($requestedFile);
    exit;
}

if (!str_starts_with($path, '/api')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__.'/app.html');
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$route = $path;

try {
    if ($route === '/api/register' && $method === 'POST') {
        $b = json_body();
        $u = trim($b['username'] ?? '');
        $p = $b['password'] ?? '';
        if (strlen($u) < 3 || strlen($p) < 6) json_response(['error'=>'Pseudo min. 3 caractères, mot de passe min. 6 caractères'], 422);
        $stmt = db()->prepare('INSERT INTO "Users"(pseudo,password) VALUES(?,?)');
        $stmt->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
        $_SESSION['user_id'] = db()->lastInsertId();
        json_response(['username'=>$u]);
    }

    if ($route === '/api/login' && $method === 'POST') {
        $b = json_body();
        $stmt = db()->prepare('SELECT * FROM "Users" WHERE pseudo=?');
        $stmt->execute([trim($b['username'] ?? '')]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($b['password'] ?? '', $user['password'])) json_response(['error'=>'Identifiants invalides'], 401);
        $_SESSION['user_id'] = $user['id'];
        json_response(['username'=>$user['pseudo']]);
    }

    if ($route === '/api/logout' && $method === 'POST') { session_destroy(); json_response(['ok'=>true]); }
    if ($route === '/api/me' && $method === 'GET') { json_response(['user'=>current_user()]); }

    if ($route === '/api/game' && $method === 'POST') {
        $user = require_user();
        $b = json_body();
        $difficulty = in_array($b['difficulty'] ?? '', ['facile','moyen','difficile'], true) ? $b['difficulty'] : 'moyen';
        $secret = fetch_word($difficulty);
        $stmt = db()->prepare('INSERT INTO games(user_id, secret_word, difficulty) VALUES(?,?,?)');
        $stmt->execute([$user['id'], $secret, $difficulty]);
        json_response(['gameId'=>db()->lastInsertId(), 'length'=>strlen($secret), 'firstLetter'=>strtoupper($secret[0]), 'maxAttempts'=>6]);
    }

    if ($route === '/api/guess' && $method === 'POST') {
        $user = require_user();
        $b = json_body();
        $guess = normalize_word($b['guess'] ?? '');
        $stmt = db()->prepare('SELECT * FROM games WHERE id=? AND user_id=?');
        $stmt->execute([$b['gameId'] ?? 0, $user['id']]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$game) json_response(['error'=>'Partie introuvable'], 404);
        if ($game['status'] !== 'playing') json_response(['error'=>'Partie terminée'], 409);
        if (strlen($guess) !== strlen($game['secret_word'])) json_response(['error'=>'Le mot doit contenir '.strlen($game['secret_word']).' lettres'], 422);
        if ($guess[0] !== $game['secret_word'][0]) json_response(['error'=>'Le mot doit commencer par '.strtoupper($game['secret_word'][0])], 422);

        $attempts = (int)$game['attempts'] + 1;
        $result = evaluate_guess($game['secret_word'], $guess);
        $won = $guess === $game['secret_word'];
        $lost = !$won && $attempts >= 6;
        $score = $won ? score_for($game['difficulty'], $attempts) : 0;

        db()->prepare('INSERT INTO guesses(game_id, guess, result_json) VALUES(?,?,?)')->execute([$game['id'], $guess, json_encode($result)]);
        db()->prepare('UPDATE games SET attempts=?, status=?, score=?, finished_at=CASE WHEN ? THEN CURRENT_TIMESTAMP ELSE finished_at END WHERE id=?')->execute([$attempts, $won?'won':($lost?'lost':'playing'), $score, ($won||$lost)?1:0, $game['id']]);
        if ($won) {
            db()->prepare('INSERT INTO "Wall of Fame" (scores, login, user_id, game_id) VALUES (?, ?, ?, ?)')->execute([$score, $user['username'], $user['id'], $game['id']]);
        }
        json_response(['result'=>$result, 'attempts'=>$attempts, 'status'=>$won?'won':($lost?'lost':'playing'), 'score'=>$score, 'secret'=>$won||$lost ? strtoupper($game['secret_word']) : null]);
    }

    if ($route === '/api/leaderboard' && $method === 'GET') {
        $rows = db()->query('SELECT login AS username, MAX(scores) AS best_score, COUNT(*) AS victories FROM "Wall of Fame" GROUP BY login ORDER BY best_score DESC, victories DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        json_response($rows);
    }

    json_response(['error'=>'Route introuvable'], 404);
} catch (Throwable $e) {
    json_response(['error' => 'Erreur serveur', 'details' => $e->getMessage()], 500);
}
