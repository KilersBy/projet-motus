<?php
function normalize_word(string $word): string {
    $word = trim($word);
    $map = [
        'À'=>'a','Â'=>'a','Ä'=>'a','Á'=>'a','Ã'=>'a','Å'=>'a','à'=>'a','â'=>'a','ä'=>'a','á'=>'a','ã'=>'a','å'=>'a',
        'Ç'=>'c','ç'=>'c','É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Ñ'=>'n','ñ'=>'n',
        'Ó'=>'o','Ò'=>'o','Ô'=>'o','Ö'=>'o','Õ'=>'o','ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
        'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ý'=>'y','Ÿ'=>'y','ý'=>'y','ÿ'=>'y'
    ];
    $word = strtolower(strtr($word, $map));
    return preg_replace('/[^a-z]/', '', $word) ?: '';
}

function difficulty_length(string $difficulty): int {
    return match($difficulty) {
        'facile' => 5,
        'moyen' => 7,
        'difficile' => 9,
        default => 7
    };
}

function local_words(int $length): array {
    $words = [
        'avion','chien','pomme','table','livre','maison','citron','pirate','orange','voyage','sourire','machine','lecture','qualite','fromage','harmonie','internet','plateforme','xylophone','algorithme','ordinateur','developpe'
    ];
    return array_values(array_filter(array_map('normalize_word', $words), fn($w) => strlen($w) === $length));
}

function read_word_from_api_payload(mixed $json, int $length): ?string {
    $candidates = [];

    if (is_string($json)) {
        $candidates[] = $json;
    } elseif (is_array($json)) {
        foreach ($json as $key => $item) {
            if (is_string($item)) {
                $candidates[] = $item;
            } elseif (is_array($item)) {
                foreach (['name', 'word', 'mot', 'label', 'value'] as $field) {
                    if (!empty($item[$field]) && is_string($item[$field])) {
                        $candidates[] = $item[$field];
                    }
                }
            }
        }
    }

    $valid = array_values(array_filter(array_map('normalize_word', $candidates), fn($w) => strlen($w) === $length));
    return $valid ? $valid[array_rand($valid)] : null;
}

function fetch_word(string $difficulty): string {
    $length = difficulty_length($difficulty);

    // API externe open source utilisée par le projet : https://trouve-mot.fr/api/size/{length}
    // Exemple : https://trouve-mot.fr/api/size/5 renvoie un mot français de 5 lettres au format JSON.
    $api = getenv('WORD_API_URL') ?: 'https://trouve-mot.fr/api/size/{length}';
    $url = str_replace('{length}', (string)$length, $api);

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: Motus-PHP/1.0\r\n"
        ]
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw !== false && trim($raw) !== '') {
        $json = json_decode($raw, true);
        $word = read_word_from_api_payload($json, $length);
        if ($word) {
            save_word_to_database($word, $difficulty);
            return $word;
        }
    }

    // Secours local pour que le jeu reste jouable si l'API externe est momentanément indisponible.
    $fallback = local_words($length);
    $word = $fallback ? $fallback[array_rand($fallback)] : 'motus';
    save_word_to_database($word, $difficulty);
    return $word;
}

function save_word_to_database(string $word, string $difficulty): void {
    try {
        $stmt = db()->prepare('INSERT OR IGNORE INTO "Mots" (word, longueur, difficulte) VALUES (?, ?, ?)');
        $stmt->execute([$word, strlen($word), $difficulty]);
    } catch (Throwable $e) {
        // Ne bloque pas une partie si l'enregistrement du mot échoue.
    }
}
