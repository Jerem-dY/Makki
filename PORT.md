# Le lexique Libanais de Makki : **Note de portabilité**

Cette note de portabilité a pour but de décrire l'environnement sur lequel le système a été testé ainsi que les étapes nécessaires à sa réinstallation sur un serveur similaire.

## Historique

| version | changements |
|---------|-------------|
| 1.0     | v. initiale |

## Spécifications techniques du serveur

La configuration mentionnée ci-dessous est celle sur laquelle a été testé le système. 

- **Système d'exploitation** : Ubuntu 18.04
- **Serveur Web** : Apache/2.4.29 
    - *libmysql - mysqlnd 5.0.12-dev*
- **Système de gestion de bases de données** : MariaDB 10.1.48-MariaDB-0ubuntu0.18.04.1
    - *Protocol version: 10*
    - *Server charset: UTF-8 Unicode (utf8)*
- **PHP** : version 7.2.24-0ubuntu0.18.04.17
    - *PHP extension: mysqliDocumentation curlDocumentation mbstringDocumentation*

## Installation du système

#### Etape 1 : Préliminaires
Assurez-vous de disposer d'un serveur prêt à l'emploi (la configuration de ce dernier ne sera pas détaillée ici).

#### Etape 2 : Ajout des fichiers
Déposez le contenu du projet dans le répertoire `public_html` (ou équivalent) du serveur, par exemple [en le clonant depuis GitHub](https://docs.github.com/fr/repositories/creating-and-managing-repositories/cloning-a-repository).

#### Etape 3 : Configuration
Créez le fichier `config/db.json` avec la structure suivante :

```json
{
    "db_host" : "127.0.0.1",
    "db_name" : "nom_de_la_db",
    "db_user" : "nom_dutilisateur",
    "db_pwd" : "mot_de_passe",
    "store_name" : "makki",
    "bnode_prefix" : "bn",
    "sem_html_formats" : "rdfa microformats"
}
```

Les deux derniers champs étant à laisser tels quels.

De la même façon, **créez le fichier `config/server.txt` et inscrivez-y seulement l'adresse du serveur**.
Par exemple, pour un serveur `www.example.com`, écrire `www.example.com`. Si le serveur est partagé, cela peut être `www.example.com/vous/` ou `www.example.com/vous/makki/` (en fonction de si vous avez plusieurs sites dans `public_html`).

#### Etape 4 : Mise en place des dépendances PHP

L'installation des modules PHP nécessite l'utilisation de **Composer**. Ce dernier doit être installé dans le dossier `/api/` du projet. Les instructions sont disponibles sur le [site officiel](https://getcomposer.org/doc/00-intro.md).

Un simple `php composer update` est ensuite suffisant pour installer les dépendances.

**/!\ NOTE :** en fonction de la version installée de *semsol/arc2*, il peut être nécessaire d'effectuer [une petite correction](https://github.com/semsol/arc2/issues/122) (un bug corrigé dans les versions plus récentes, mais indisponibles pour les versions de PHP < 8.0).
Dans le fichier `ARC2_StoreLoadQueryHandler.php`, ligne 228, il faut changer `if (false !== empty($binaryValue)) {` par `if (false == empty($binaryValue)) {` (égal au lieu de strictement différent).

#### Etape 4 : Ajout d'un administrateur

Rendez-vous une première fois sur la version en ligne du site ; cela mettra en place la base de données automatiquement. 
Une fois cela fait, accédez à votre base de données (via PHPmyadmin, en ligne de commande ou autre...) et ajoutez un enregistrement dans la table `admin` : 
- numéro (par exemple `0`)
- login (par exemple `admin`)
- mot de passe **haché** : choisissez un mot de passe puis hachez-le ; vous pouvez passer par https://phppasswordhash.com/ (en choisissant `PASSWORD_BCRYPT`) ou bien mettre en place votre propre script pour générer le hash.