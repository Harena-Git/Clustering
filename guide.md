# GUIDE RAPIDE (copy / paste) — Lancer le projet Clustering

But: donner les commandes exactes et simples pour démarrer le projet dans le navigateur.

Important: copiez-collez chaque bloc, dans l'ordre. Exécutez dans un terminal bash. Si un bloc donne une erreur, copiez-collez l'erreur ici.

---

## 1) Installer les outils (Postgres, PHP, HAProxy)

Copiez tout et collez dans le terminal :

```bash
sudo apt update
sudo apt install -y postgresql postgresql-contrib php php-cli php-pgsql haproxy
```

Explication simple: on installe la base de données (Postgres), PHP (pour exécuter le site) et HAProxy (pour tester le load-balancing).

---

## 2) Démarrer PostgreSQL

```bash
sudo systemctl start postgresql
sudo systemctl enable postgresql
sudo systemctl status postgresql
```

Explication simple: on lance Postgres et on vérifie qu'il fonctionne.

---

## 3) Créer 2 bases (A et B) et un utilisateur `webuser`

Copiez-collez:

```bash
sudo -u postgres psql <<'PSQL'
CREATE DATABASE cluster_session_a;
CREATE DATABASE cluster_session_b;
CREATE USER webuser WITH PASSWORD 'change_me';
GRANT ALL PRIVILEGES ON DATABASE cluster_session_a TO webuser;
GRANT ALL PRIVILEGES ON DATABASE cluster_session_b TO webuser;
\q
PSQL
```

Explication simple: on crée deux coffres (bases de données) vides et un compte `webuser` pour s'y connecter. Remplacez `change_me` par un vrai mot de passe.

---

## 4) Créer le schéma (tables) et l'appliquer aux deux bases

Ce bloc crée un fichier `schema.sql` dans le dossier courant et l'applique aux deux bases.

```bash
cat > schema.sql <<'SQL'
-- table pour sessions
CREATE TABLE IF NOT EXISTS php_session (
    id TEXT PRIMARY KEY,
    data BYTEA,
    date_change TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- table demo pour CRUD
CREATE TABLE IF NOT EXISTS items (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    content TEXT,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- table serveurs
CREATE TABLE IF NOT EXISTS servers (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    host TEXT NOT NULL,
    port INTEGER NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
SQL

sudo -u postgres psql -d cluster_session_a -f schema.sql
sudo -u postgres psql -d cluster_session_b -f schema.sql
```

Explication simple: on crée trois tableaux (sessions, items, servers) dans chaque base.

---

## 5) Créer `env.sh` (variables d'environnement)

Créez et chargez les variables pour que l'application sache où se connecter.

```bash
cat > env.sh <<'ENV'
# DB A
export DB1_HOST=127.0.0.1
export DB1_NAME=cluster_session_a
export DB1_USER=webuser
export DB1_PASSWORD='change_me'

# DB B
export DB2_HOST=127.0.0.1
export DB2_NAME=cluster_session_b
export DB2_USER=webuser
export DB2_PASSWORD='change_me'

# rétro-compatibilité
export DB_HOST=127.0.0.1
export DB_NAME=cluster_session_a
export DB_USER=webuser
export DB_PASSWORD='change_me'
ENV

# charger maintenant
source env.sh
```

Explication simple: `env.sh` stocke l'adresse et le mot de passe des bases. `source env.sh` active ces infos pour votre terminal.

---

## 6) Lancer les serveurs PHP (dans le bon dossier)

Très important: positionnez-vous dans le dossier du projet (celui qui contient `index.php`).

```bash
cd ~/Documents/Mr_Naina/Clustering
source env.sh

# Ouvrir un premier terminal et lancer:
php -S 127.0.0.1:8001 -t .

# Ouvrir un second terminal et lancer:
php -S 127.0.0.1:8002 -t .
```

Si vous préférez lancer en arrière-plan (pas recommandé pour développer car on ne voit pas les logs):

```bash
cd ~/Documents/Mr_Naina/Clustering
source env.sh
nohup php -S 127.0.0.1:8001 -t . > /tmp/web1.log 2>&1 &
nohup php -S 127.0.0.1:8002 -t . > /tmp/web2.log 2>&1 &
```

Explication simple: on démarre deux mini-serveurs web qui serviront les fichiers du projet.

---

## 7) Vérifier que les serveurs tournent

```bash
# lister les serveurs php
ps aux | grep "php -S" | grep -v grep
# essayer une requête
curl -I http://127.0.0.1:8001
```

Vous devez voir `HTTP/1.1 200 OK` ou la page d'accueil.

---

## 8) HAProxy — si vous voulez un seul point d'entrée (optionnel)

Si Apache utilise le port 80, soit arrêtez Apache, soit faites HAProxy écouter 8080.

Option A — arrêter Apache (libérer le port 80):

```bash
sudo systemctl stop apache2
sudo systemctl disable apache2   # si vous voulez l'empêcher de démarrer au boot
```

Option B — faire HAProxy écouter 8080 (édition simple):

```bash
# sauvegarder la config actuelle
sudo cp /etc/haproxy/haproxy.cfg /etc/haproxy/haproxy.cfg.bak

# écrire une config minimale qui écoute 8080
sudo tee /etc/haproxy/haproxy.cfg > /dev/null <<'HAP'
global
    log /dev/log    local0
    maxconn 4096

defaults
    log     global
    mode    http
    option  httplog
    option  dontlognull
    timeout connect 5000
    timeout client  50000
    timeout server  50000

frontend http_front
    bind *:8080
    default_backend web_back

backend web_back
    balance roundrobin
    server web1 127.0.0.1:8001 check
    server web2 127.0.0.1:8002 check
HAP

# vérifier et redémarrer
sudo haproxy -c -f /etc/haproxy/haproxy.cfg
sudo systemctl restart haproxy
sudo systemctl status haproxy
```

Test:

```bash
curl -I http://127.0.0.1:8080
```

Explication simple: HAProxy va recevoir les requêtes et les envoyer aux serveurs 8001 et 8002.

---

## 9) Ouvrir dans le navigateur

- Sans HAProxy : ouvrez http://127.0.0.1:8001 ou http://127.0.0.1:8002
- Avec HAProxy sur 8080 : ouvrez http://127.0.0.1:8080

Si vous voyez la page d'accueil du projet, c'est bon.

---

## 10) Vérifier les tables (psql)

```bash
# lister les items dans A
psql -h 127.0.0.1 -U webuser -d cluster_session_a -c "SELECT * FROM items;"

# lister dans B
psql -h 127.0.0.1 -U webuser -d cluster_session_b -c "SELECT * FROM items;"
```

Explication simple: on vérifie que les données existent dans chaque base.

---

## 11) Problèmes fréquents rapides

- Mot de passe DB incorrect → `password authentication failed` : changez `env.sh` ou modifiez le mot de passe PostgreSQL :

```bash
sudo -u postgres psql -c "ALTER USER webuser WITH PASSWORD 'votre_mdp';"
source env.sh
```

- HAProxy ne démarre pas (port occupé) → stoppez Apache ou changez le port HAProxy (voir étape 8).
- 404 Not Found → avez-vous lancé `php -S` depuis le dossier `~/Documents/Mr_Naina/Clustering` ? Si non, relancez-le depuis ce dossier.

---

## 12) Si vous voulez que je génère les fichiers automatiquement

Je peux créer dans le projet : `schema.sql` (déjà généré ici si vous avez suivi), `env.sh`, `dbMultiPdo.php` (ébauche) et `manage.php` (ébauche CRUD). Dites "Crée dbMultiPdo/manage" si vous voulez que je l'ajoute.

---

C'est tout. Si vous collez ces blocs dans l'ordre, le projet doit démarrer et être accessible dans votre navigateur. Si une commande échoue, copiez l'erreur et collez-la ici pour que je vous aide.
