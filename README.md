# projet-motus

## Base de données

La base de données est automatiquement initialisée au lancement du projet avec Docker (migrations exécutées automatiquement).

Pour lancer le projet

Cloner le repo :

git clone https://github.com/KilersBy/projet-motus
cd projet-motus

Copier le fichier de configuration :

cp .env.example .env

Lancer avec Docker :

docker compose up --build

Accès à l'app via : http://localhost:3000
