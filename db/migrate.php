<?php
$dbPath = getenv('DB_PATH') ?: __DIR__ . '/../data/motus.sqlite';
$dir = dirname($dbPath);
if (!is_dir($dir)) mkdir($dir, 0777, true);
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec('CREATE TABLE IF NOT EXISTS "Users" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pseudo TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL,
  numero_secu TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "Mots" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  word TEXT NOT NULL UNIQUE,
  longueur INTEGER NOT NULL,
  difficulte TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS "Wall of Fame" (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  scores INTEGER NOT NULL,
  login TEXT NOT NULL,
  user_id INTEGER,
  game_id INTEGER,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES "Users"(id)
);

CREATE TABLE IF NOT EXISTS games (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  secret_word TEXT NOT NULL,
  difficulty TEXT NOT NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  max_attempts INTEGER NOT NULL DEFAULT 6,
  status TEXT NOT NULL DEFAULT "playing",
  score INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at TEXT,
  FOREIGN KEY(user_id) REFERENCES "Users"(id)
);

CREATE TABLE IF NOT EXISTS guesses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  game_id INTEGER NOT NULL,
  guess TEXT NOT NULL,
  result_json TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(game_id) REFERENCES games(id)
);');
