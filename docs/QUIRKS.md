# Quirks & pièges connus

Comportements non-évidents découverts au fil du projet. Un H2 par quirk, avec une date.

**Si tu en découvres un nouveau pendant une session : ajoute-le ici dès la découverte, pas plus tard.**

> Les pièges Saloon génériques (probe booléen + `AlwaysThrowOnErrors`, collision `$body`,
> `resolveBaseUrl()` ignoré, multipart resource) sont dans `SALOON_LIBRARY_DESIGN.md` §8.
> Ici on consigne les pièges **spécifiques à ce projet / cet environnement**.

---

## Saloon v3 entièrement vulnérable — fixé en v4 uniquement (2026-06-23)

**Découvert** : au premier `composer install`.

**Symptôme** : `composer install` refuse `saloonphp/saloon ^3.0` — toutes les versions v3
(v3.0.0 → v3.15.0) bloquées par 3 security advisories.

**Cause** : CVE-2026-33942 (*high*, désérialisation `AccessTokenAuthenticator`), CVE-2026-33183
(path traversal fixtures), CVE-2026-33182 (SSRF via URL absolue surchargeant la base URL).
Toutes `Affected: <4.0.0` — aucun v3 patché.

**Workaround** : passage à `saloonphp/saloon ^4.0`. `composer audit` vert. Spec répercutée v3→v4.

**Référence** : `composer.json`, advisories Packagist `PKSA-xnj5-w74d-6wmz` / `-rnpm-45mg-w6ht` / `-5szq-gvrg-ttfq`.

## `mbstring` requis pour PHPUnit (2026-06-23)

**Découvert** : premier `vendor/bin/phpunit`.

**Symptôme** : « PHPUnit requires the "mbstring" extension, but the "mbstring" extension is not available. »

**Cause** : l'extension `mbstring` n'était pas installée sur l'hôte (PHP 8.5).

**Workaround** : `sudo apt install php8.5-mbstring` puis vérifier `php -m | grep mbstring`.

**Référence** : hôte `/srv/AdrienGras/umami-php`.

## phpstan « No files found » tant que `src/` est vide (2026-06-23)

**Découvert** : mise en place de la porte de validation.

**Symptôme** : `phpstan analyse` retourne un **code non-zéro** avec « No files found to analyse »
avant l'écriture de tout code (étape 5), ce qui ferait un faux rouge sur la porte de commit.

**Cause** : phpstan considère une absence de fichiers comme une erreur.

**Workaround** : `scripts/check.sh` traite spécifiquement ce message exact en « skip » (toute
*vraie* erreur phpstan reste rouge). Le skip disparaît dès qu'il y a du code dans `src/`.

**Référence** : `scripts/check.sh`.
