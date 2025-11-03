# Clustering
Projet a guiter

## Configuration

Cette application utilise PostgreSQL pour stocker les sessions. Vous pouvez configurer la connexion via des variables d'environnement :

- `DB_HOST` (par défaut `127.0.0.1`)
- `DB_NAME` (par défaut `cluster_session`)
- `DB_USER` (par défaut `postgres`)
- `DB_PASSWORD` (par défaut vide)

Exemple (Linux / bash) :

```bash
export DB_HOST=127.0.0.1
export DB_NAME=cluster_session
export DB_USER=postgres
export DB_PASSWORD="votre_mot_de_passe"
php -S localhost:5000
```

Si la connexion à la base échoue, le gestionnaire de session tombe en "fallback" non persistant (les sessions ne seront pas conservées entre les requêtes) et une erreur sera loggée dans le journal PHP.
