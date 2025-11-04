# Guide de lancement — Projet Clustering

Ce guide explique comment lancer localement le projet sur Ubuntu, créer deux bases PostgreSQL (préparées pour une configuration master-to-master), configurer HAProxy pour tester la répartition des requêtes web, et démarrer les serveurs PHP pour tester l'interface.

## Prérequis
- Ubuntu (vous êtes déjà sous Ubuntu)
- PostgreSQL installé
- HAProxy installé (vous avez indiqué l'avoir déjà)
- PHP (>=7.4) avec PDO pgsql
- Accès sudo

## 1) Démarrer PostgreSQL

```bash
sudo systemctl start postgresql
sudo systemctl status postgresql
```

## 2) Créer les deux bases et les utilisateurs

Nous allons créer deux bases identiques : `cluster_session_a` et `cluster_session_b`.
Remplacez `postgres` et les mots de passe par vos choix.

```bash
# se connecter comme user postgres
sudo -u postgres psql

-- Dans psql:
CREATE DATABASE cluster_session_a;
CREATE DATABASE cluster_session_b;
-- Créer un utilisateur dédié (si nécessaire)
CREATE USER webuser WITH PASSWORD 'change_me';
GRANT ALL PRIVILEGES ON DATABASE cluster_session_a TO webuser;
GRANT ALL PRIVILEGES ON DATABASE cluster_session_b TO webuser;
\q
```

## 3) Appliquer le schéma (tables communes)

Exécutez le SQL suivant sur les deux bases (ou adaptez `script.sql`).

SQL (schema minimal à exécuter sur chaque base) :

```sql
-- table pour sessions
CREATE TABLE IF NOT EXISTS php_session (
    id TEXT PRIMARY KEY,
    data BYTEA,
    date_change TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- table de démonstration (items CRUD)
CREATE TABLE IF NOT EXISTS items (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    content TEXT,
    updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- table pour enregister les serveurs / health
CREATE TABLE IF NOT EXISTS servers (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    host TEXT NOT NULL,
    port INTEGER NOT NULL,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
```

Pour appliquer sur chaque base (exemple) :

```bash
# depuis l'utilisateur postgres
sudo -u postgres psql -d cluster_session_a -f script.sql
sudo -u postgres psql -d cluster_session_b -f script.sql
```

Si `script.sql` n'a pas encore le schéma, créez un fichier `schema.sql` contenant le bloc SQL ci-dessus et utilisez-le.

## 4) Options pour mettre en place la réplication master-to-master

Multi-master avec PostgreSQL demande une solution externe (Postgres ne propose pas un mode multi-master natif simple). Deux approches courantes :

A) Bucardo (asynchrone, multi-master) — adapté pour réplication bi-directionnelle de tables sélectionnées.
- Site : https://bucardo.org
- Installation : souvent via paquet ou CPAN. Exemple d'idée :

```bash
sudo apt install bucardo   # si disponible
# ou suivez la doc officielle pour installation via cpan
```

Configuration (très abrégée) :
- Enregistrez les deux bases dans Bucardo
- Créez un `sync` entre les tables `items`, `servers`, `php_session` en mode `push,pull` pour les deux DBs

B) pglogical ou logical replication
- pglogical permet la réplication logique; peut être utilisé en bidirectionnel mais la configuration est plus avancée.
- Recommandé pour postes expérimentés et tests.

Remarque : en local pour tests, vous pouvez simuler la double écriture depuis l'application (écrire manuellement sur les deux DBs dans la même transaction/application) plutôt que d'installer une solution de réplication complète.

=> Pour débuter rapidement et tester l'interface, je recommande d'implémenter côté application la double-écriture (le projet contiendra `dbMultiPdo.php` qui écrit les changements sur les deux bases). Ensuite, plus tard, vous pouvez remplacer par Bucardo/pglogical pour la réplication automatique.

## 5) Variables d'environnement (configuration PHP)

Le code actuel lit ces variables d'environnement pour la connexion DB. Exemple :

```bash
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

# Si vous avez besoin de config pour le gestionnaire de session existant
export DB_HOST=127.0.0.1
export DB_NAME=cluster_session_a
export DB_USER=webuser
export DB_PASSWORD='change_me'
```

Placez ces exports dans votre shell ou dans un script `env.sh` puis sourcez-le :

```bash
source env.sh
```

## 6) Démarrer les serveurs Web (local test)

Pour simuler plusieurs serveurs web (à mettre derrière HAProxy), lancez deux instances du serveur PHP intégré sur des ports différents depuis le répertoire du projet :

```bash
# Terminal 1
export DB_HOST=127.0.0.1
export DB_NAME=cluster_session_a
export DB_USER=webuser
export DB_PASSWORD='harena'
php -S 127.0.0.1:8001 -t .

# Terminal 2
export DB_HOST=127.0.0.1
export DB_NAME=cluster_session_b
export DB_USER=webuser
export DB_PASSWORD='harena'
php -S 127.0.0.1:8002 -t .
```

Idée : chaque instance peut être configurée pour pointer vers l'une ou l'autre base (ou écrire sur les deux via `dbMultiPdo.php`). Pour tests rapides, vous pouvez faire l'instance 1 pointer vers DB A et instance 2 point vers DB B.

## 7) Exemple de configuration HAProxy

Nous vous donnons deux petits exemples : HTTP load-balancer (frontend web) et TCP proxy pour PostgreSQL (optionnel).

Fichier `/etc/haproxy/haproxy.cfg` (extrait pour HTTP) :

```
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
    bind *:80
    default_backend web_back

backend web_back
    balance roundrobin
    server web1 127.0.0.1:8001 check
    server web2 127.0.0.1:8002 check
```

Après modification :

```bash
sudo systemctl restart haproxy
sudo systemctl status haproxy
```

Tester :

```bash
curl -I http://127.0.0.1/
# ou ouvrir http://localhost/ dans votre navigateur
```

Exemple TCP proxy pour PostgreSQL (si vous voulez utiliser HAProxy pour basculer entre Postgres instances) :

```
frontend pgsql_front
    bind *:5433
    mode tcp
    default_backend pgsql_back

backend pgsql_back
    mode tcp
    balance roundrobin
    server db_a 127.0.0.1:5432 check
    server db_b 127.0.0.1:54322 check
```

Remarque : pour la plupart des setups de réplication, vous n'utiliserez pas HAProxy comme master-master controller — HAProxy sert surtout à load-balancer les connexions (lecture) ou faire du failover.

## 8) Tester l'interface et CRUD

- Ouvrez votre navigateur à `http://localhost/` (si HAProxy sur :80) ou directement `http://127.0.0.1:8001`/`:8002`.
- Utilisez le formulaire dans `index.php` pour vérifier les sessions.
- Si vous implémentez `manage.php` et `dbMultiPdo.php`, testez la création/suppression d'items et vérifiez qu'elles apparaissent dans les deux bases via `psql -d cluster_session_a -c "SELECT * FROM items;"` et pareil pour `cluster_session_b`.

## 9) Commandes utiles

```bash
# vérifier processus postgres
sudo ss -ltnp | grep postgres

# se connecter à la DB
psql -h 127.0.0.1 -U webuser -d cluster_session_a

# lister tables
psql -h 127.0.0.1 -U webuser -d cluster_session_a -c '\dt'
```

## 10) Prochaines étapes recommandées

- Implémenter `dbMultiPdo.php` : abstraire 2 connexions et fournir des méthodes `transactionalWrite($sql, $params)` qui exécutent les écritures sur les deux DBs (si l'une échoue, logguer et gérer le rollback/compensation selon la stratégie choisie).
- Ajouter `manage.php` : interface CRUD qui invoque `dbMultiPdo` pour écrire sur les deux DBs.
- Pour la réplication en production, installez et configurez Bucardo ou pglogical selon vos besoins et testez la résolution de conflits.

---

Si vous voulez, je peux maintenant :
- Générer `schema.sql` et l'ajouter au projet (basé sur le SQL ci-dessus),
- Créer une ébauche de `dbMultiPdo.php` qui effectue la double écriture,
- Créer une ébauche de `manage.php` (interface web CRUD simple),
- Fournir un exemple complet de configuration Bucardo (si vous le souhaitez).

Dites-moi quelle(s) étape(s) vous voulez que j'implémente automatiquement maintenant.