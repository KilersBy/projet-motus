<?php
function evaluate_guess(string $secret, string $guess): array {
    $secret = str_split($secret);
    $guessChars = str_split($guess);
    $result = array_fill(0, count($secret), 'absent');
    $remaining = [];
    foreach ($secret as $i => $ch) {
        if (($guessChars[$i] ?? null) === $ch) $result[$i] = 'correct';
        else $remaining[$ch] = ($remaining[$ch] ?? 0) + 1;
    }
    foreach ($guessChars as $i => $ch) {
        if ($result[$i] === 'correct') continue;
        if (($remaining[$ch] ?? 0) > 0) { $result[$i] = 'present'; $remaining[$ch]--; }
    }
    return array_map(fn($ch, $state) => ['letter' => strtoupper($ch), 'state' => $state], $guessChars, $result);
}
function score_for(string $difficulty, int $attempts): int {
    $base = ['facile'=>100, 'moyen'=>150, 'difficile'=>220][$difficulty] ?? 100;
    return max(10, $base - (($attempts - 1) * 20));
}
