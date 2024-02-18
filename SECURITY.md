# Le lexique Libanais de Makki : **Note de sécurité**

Cette note de sécurité décrit les mesures qui ont été prises ou restent à prendre **concernant la sécurité du système au niveau applicatif**.

## Historique

| version | date | changements |
|---------|-----|-------------|
| 1.0     |23/02/2024| v. initiale |

## Notes

Les headers HTTP par défaut permettant la plupart des protections décrites ci-dessous sont trouvables dans [config/headers.json](config/headers.json). L'utilisation des fonctions cryptographiques est faite dans [api/jwt.php](api/jwt.php).

## Checklist

[x] **jeton de session** signé et chiffré, avec date de péremption   
[x] **jeton anti-csrf** (*nonce*) en double-envoi, pour les requêtes POST et DELETE (formulaires)  
[x] génération d'un **trousseau de clés** et rotations tous les mois   
[x] interdire les éléments provenant de sources différentes (y compris ressources inlines)  
[x] interdire l'intégration d'une page du site sur un autre site (possibilité d'autoriser certaines sources)  
[x] interdire l'utilisation du micro, de la caméra, de la géolocalisation ou des systèmes de paiement  
[x] désactiver l'adaptation du type de contenu  


[ ] chiffrement des identifiants avant envoi  
[ ] transport chiffré des données (HTTPS) *(configuration niveau serveur, ne concerne pas ce projet)*  
[ ] rediriger les messages d'erreur vers un **système de log** (par exemple dans .htaccess : 
```# On désactive l'affichage des erreurs PHP chez le client
# On désactive l'affichage des erreurs PHP chez le client
php_flag display_startup_errors off
php_flag display_errors off
php_flag html_errors off

# Mise en place du logging
php_flag log_errors on
php_value error_log ./logs/errors.log```)  