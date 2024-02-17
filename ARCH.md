# Le lexique Libanais de Makki : **Note d'architecture**

Cette note d'architecture décrit la structure du système et son fonctionnement global.

## Historique

| version | changements |
|---------|-------------|
| 1.0     | v. initiale |


## Arborescence

*Seuls les dossiers et fichiers directement impliqués dans le fonctionnement du système (en incluant l'extraction et pré-traitement des données) seront notés ici. Le présent fichier, .gitignore et consorts ne sont pas inclus.*

### . (racine)

|  | Description |
|---------|-------------|
|[`.htaccess`](.htaccess)|Permet de définir `index.php` comme unique point d'entrée à partir de ce dossier. |
|[`index.php`](index.php)|Point d'entrée du système. Récupère la configuration et lance le traitement de la requête utilisateur.|
|[*api*](api)|Dossier contenant les fichiers PHP pour le traitement des requêtes, la génération des documents de sortie, l'interaction avec la base de données et la sécurisation des échanges. |
|[*config*](config)|Contient les différents fichiers de configuration du système.|
|[*html*](html)|Contient les ressources nécessaires à la génération des sorties, comme les patrons html, les feuilles de style, les scripts et les polices d'écriture.|
|[*traductions*](traductions)|Contient les fichiers de traduction du site.|

### ./api

|  | Description |
|---------|-------------|
|[`handler.php`](/api/handler.php)| Coordonne le traitement des requêtes en mettant en oeuvre les différentes étapes de traitement. |
|[`parser.php`](/api/parser.php)| Met à disposition des fonctions permettant d'extraire les informations des URI, des queries, et des headers Accept et Accept-Language. |
|[`db.php`](/api/db.php)| Permet d'interagir avec la base de données. |
|[`jwt.php`](/api/jwt.php)| Introduit des classes permettant de chiffrer, déchiffrer et signer des JSON Web Tokens, ainsi que de générer les clés nécessaires. |
|[`upload.php`](/api/upload.php)| Classe permettant de gérer l'upload de fichiers. |
|[`tools.php`](/api/tools.php)| Fichier fourre-tout permettant de stocker des fonctions diverses. |
|[`composer.json`](/api/composer.json)| Fichier listant les dépendances du projet. Utilisé par Composer pour installer ces dépendances. |
|[*serializers*](/api/serializers)| Contient les classes de génération des documents de sortie. |
|[*simple_html_dom*](/api/simple_html_dom)| Dépendance externe indisponible avec Composer, et permettant de lire et modifier du HTML. |

### ./api/serializers

|  | Description |
|---------|-------------|
|[`html.php`](/api/serializers/html.php)| Permet de générer les sorties en HTML. |

### ./config

|  | Description |
|---------|-------------|
|[`db.json`](/config/db.json)| *(à créer, voir [la note de portabilité](PORT.md#etape-3--configuration))* Permet de configurer l'accès à la base de données. |
|[`db.sql`](/config/db.sql)| Script permettant de mettre en place la base de données à la première utilisation. |
|[`headers.json`](/config/headers.json)| Définit les headers HTTP par défaut, à inclure dans chaque requête. |
|[`pages.json`](/config/pages.json)| Permet d'associer un patron HTML à une collection (premier élément de l'URI si pas de langue spécifiée). |
|[`server.txt`](/config/server.txt)| *(à créer, voir [la note de portabilité](PORT.md#etape-3--configuration))* Permet au système de connaître l'URL de base, sans le path. Cela est particulièrement nécessaire dans le cas où le site se trouve dans un sous-dossier de *public_html*. |

### ./html

|  | Description |
|---------|-------------|
|`*.html`||
|[*fonts*](/html/fonts)||
|[*images*](/html/images)||
|[*scripts*](/html/scripts)||
|[*styles*](/html/styles)||