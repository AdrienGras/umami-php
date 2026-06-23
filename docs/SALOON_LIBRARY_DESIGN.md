# Design pattern — Librairies d'API HTTP avec Saloon (PHP)

> Document de référence portable, à destination d'un assistant (ou d'un dev) travaillant sur un **autre projet PHP**.
> Il décrit, de façon autonome, le pattern qu'on utilise pour intégrer un service HTTP tiers (traduction, géocodage, génération PDF, IA, transporteur, microservice interne, …) via la librairie [Saloon](https://docs.saloon.dev/) v4 (pattern éprouvé sur Saloon v3, transposé en v4 — cf. ⚠ §7.4 pour la surcharge d'URL absolue, durcie en v4 suite à CVE-2026-33182).
>
> Ce pattern a été éprouvé sur ~6 intégrations en production (REST simple, multipart upload, réponses binaires, OAuth multi-realm). Il est volontairement **transport-only** : la lib parle HTTP, rien d'autre.

---

## 1. Principe directeur : transport-only

Une lib d'API **ne fait que du transport**. Elle :

- ouvre la connexion, authentifie, envoie la requête, mappe la réponse ou lève une exception typée ;
- **ne connaît aucune entité métier**, ne persiste rien, ne décide rien.

Tout ce qui est « rapprocher avec une entité `Product`, appliquer un seuil de confiance, persister un résultat, déclencher un workflow » vit dans une **couche métier séparée** (un service applicatif qui *consomme* la lib). Cette séparation est la règle la plus importante du pattern : elle rend la lib réutilisable, testable, et remplaçable.

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  Service métier │ ──▶ │  Lib API (Saloon) │ ──▶ │  Service tiers   │
│  (entités, DB)  │     │  transport-only   │     │  (HTTP REST)     │
└─────────────────┘     └──────────────────┘     └─────────────────┘
```

---

## 2. Découpage en couches

Quatre couches, chacune avec une responsabilité unique :

| Couche | Rôle | Ce qu'elle NE fait PAS |
|---|---|---|
| **Connector** | Transport + config globale (base URL, headers, timeout, auth, debug). Point d'entrée unique. | Aucune logique d'appel précis. |
| **Entrypoint** | Façade métier : transforme des arguments « propres » en `Request`, groupe les appels d'un domaine. **C'est ici que vivent les transformations** (mapping, normalisation, gardes d'entrée). | Pas d'I/O brute, pas de config transport. |
| **Request** | Description « bête » d'un appel HTTP unique : méthode, endpoint, body/query, headers spécifiques. Reçoit des valeurs déjà prêtes. | Aucune transformation métier. |
| **Response / Exception** | Mappe une réponse en échec vers une exception domaine typée de la lib. | Aucune logique métier. |

**Règle mnémotechnique** : la `Request` est stupide, l'`Entrypoint` est intelligent, le `Connector` est l'infrastructure.

---

## 3. Arborescence des fichiers

```
src/Libraries/<Lib>API/
  <Lib>API.php                      ← le Connector (point d'entrée unique)
  Entrypoints/
    Impl/AbstractEntrypoint.php     ← base readonly, détient le Connector
    <Domain>Entrypoint.php          ← façade métier groupant des Requests
  Requests/<Domain>/<Action>.php    ← une classe Saloon Request par appel HTTP
  Responses/<Lib>APIResponse.php    ← Response custom → toException()
  Exceptions/<Lib>APIException.php  ← exception domaine de la lib
  Middleware/*.php                  ← (optionnel) auth, injection de champ, …
  Utils/*.php                       ← (optionnel) helpers purs (mappers, factories)
  DTO/<Domain>/Input|Output/*.php   ← (optionnel) DTO typés entrée/sortie
  Enums/*.php                       ← (optionnel) enums backed miroir du contrat
```

Adapte `src/Libraries/` au layout de ton projet (`src/Integration/`, `lib/`, …). Ce qui compte, c'est le **découpage interne** et les responsabilités, pas le chemin racine.

---

## 4. Squelettes

### 4.1 Connector

```php
<?php

namespace App\Libraries\MyServiceAPI;

use App\Libraries\MyServiceAPI\Entrypoints\FooEntrypoint;
use App\Libraries\MyServiceAPI\Responses\MyServiceAPIResponse;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class MyServiceAPI extends Connector
{
    use AlwaysThrowOnErrors;  // toute réponse failed() lève automatiquement
    use AcceptsJson;          // Accept: application/json par défaut

    public readonly FooEntrypoint $foo;

    /** @var class-string|null */
    protected ?string $response = MyServiceAPIResponse::class;

    public function __construct(
        public readonly string $baseUrl = 'https://api.example.com',
        public readonly string $apiKey = '',
        public readonly bool $useDebug = false,
    ) {
        if (true === $this->useDebug) {
            $this->debug();
        }

        // Auth Bearer standard :
        // $this->withTokenAuth($this->apiKey);
        // Auth hors-standard (clé dans le body, header custom, OAuth) → middleware (cf. §5)

        $this->foo = new FooEntrypoint($this);
    }

    /** @return array<string, string> */
    public function defaultHeaders(): array
    {
        return ['User-Agent' => 'monpaquet/1.0'];
    }

    /** @return array<string, mixed> */
    public function defaultConfig(): array
    {
        return ['timeout' => 60];
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
```

**Points clés** :
- Les entrypoints sont des propriétés `public readonly` instanciées dans le constructeur → API d'usage `$api->foo->get(...)`.
- `$response` pointe vers la Response custom : tout passe par le mapping d'erreur.
- Le constructeur prend ses scalaires (`baseUrl`, `apiKey`, `useDebug`) par **injection** — voir §6 pour le câblage DI.

### 4.2 AbstractEntrypoint + Entrypoint

```php
<?php

namespace App\Libraries\MyServiceAPI\Entrypoints\Impl;

use App\Libraries\MyServiceAPI\MyServiceAPI;

abstract readonly class AbstractEntrypoint
{
    public function __construct(
        protected readonly MyServiceAPI $api,
    ) {
    }
}
```

```php
<?php

namespace App\Libraries\MyServiceAPI\Entrypoints;

use App\Libraries\MyServiceAPI\Entrypoints\Impl\AbstractEntrypoint;
use App\Libraries\MyServiceAPI\Requests\Foo\GetFoo;
use App\Libraries\MyServiceAPI\Responses\MyServiceAPIResponse;

readonly class FooEntrypoint extends AbstractEntrypoint
{
    public function get(string $id): MyServiceAPIResponse
    {
        // C'est ICI que vivent les transformations métier (mapping de codes,
        // normalisation, gardes d'entrée). La Request reçoit des valeurs prêtes.
        return $this->api->send(new GetFoo(id: $id));
    }
}
```

### 4.3 Request

POST avec body JSON :

```php
<?php

namespace App\Libraries\MyServiceAPI\Requests\Foo;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateFoo extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $name,
        private readonly ?int $size = null,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/foo';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        $body = ['name' => $this->name];

        if (null !== $this->size) {
            $body['size'] = $this->size; // omettre les optionnels nuls
        }

        return $body;
    }
}
```

- **GET avec query params** : pas de `HasBody`, `protected Method $method = Method::GET;`, et `defaultQuery(): array` au lieu de `defaultBody()`.
- **Endpoint dynamique** : interpoler dans `resolveEndpoint()` (`return "/foo/{$this->id}";`).

### 4.4 Response + Exception

```php
<?php

namespace App\Libraries\MyServiceAPI\Responses;

use App\Libraries\MyServiceAPI\Exceptions\MyServiceAPIException;
use Saloon\Http\Response;

class MyServiceAPIResponse extends Response
{
    public function toException(): ?\Throwable
    {
        if ($this->failed()) {
            $body = $this->psrResponse?->getBody()?->getContents();

            return new MyServiceAPIException($this, $body, 0, $this->getSenderException());
        }

        return null;
    }
}
```

```php
<?php

namespace App\Libraries\MyServiceAPI\Exceptions;

use Saloon\Exceptions\Request\RequestException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MyServiceAPIException extends RequestException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return $this->getStatus();
    }

    /** @return array<string, array<string>> */
    public function getHeaders(): array
    {
        return $this->getResponse()->headers()->all();
    }
}
```

> `implements HttpExceptionInterface` est spécifique à Symfony (l'exception devient mappable vers un status HTTP par le framework). Hors Symfony, retire cette interface et garde juste l'exception domaine typée.

---

## 5. Authentification

| Cas | Solution |
|---|---|
| **Bearer standard** | `$this->withTokenAuth($this->apiKey)` dans le constructeur du Connector. |
| **Clé dans le body JSON** | Middleware Connector qui injecte le champ dans chaque POST/PUT/PATCH. Ne **jamais** polluer les Requests avec la clé. |
| **Header custom** | Middleware Connector `$this->middleware()->onRequest(...)`. |
| **OAuth `client_credentials`** | Middleware qui récupère/cache un token et injecte le `Bearer`. La Request d'obtention du token est **exclue** du middleware (anti-récursion). |

### Exemple : middleware d'injection (clé dans le body)

```php
$this->middleware()->onRequest(function (Request $request): void {
    if ($request instanceof HasBody) {
        $request->body()->add('api_key', $this->apiKey);
    }
});
```

### OAuth multi-realm (plusieurs jeux d'identifiants)

Si l'API a plusieurs « domaines » d'auth (ex. un realm *shipping* et un realm *tracking* avec des credentials distincts) :

- un `TokenStore` cache un token **par realm** (TTL = `expires_in` − marge de 60 s) ;
- un `OAuthMiddleware` choisit le realm selon un **marqueur d'interface** sur la Request (ex. une Request qui `implements RequiresTrackingAuth` → realm tracking, sinon realm par défaut) ;
- la Request qui demande le token est exclue du middleware.

Les Entrypoints métier ne touchent **jamais** le token : tout est transparent.

---

## 6. Câblage DI (Symfony) — étape OBLIGATOIRE, piège n°1

Le bloc d'autowiring `App\:` enregistre bien la classe du Connector comme service, **mais ne câble pas ses arguments scalaires** (`$baseUrl`, `$apiKey`, …). Sans binding explicite, **les valeurs par défaut du constructeur s'appliquent silencieusement** — tu tapes `https://api.example.com` au lieu de ta vraie URL, sans aucune erreur.

```yaml
# config/services.yaml
App\Libraries\MyServiceAPI\MyServiceAPI:
  arguments:
    $baseUrl: '%env(MYSERVICE_API_ENTRYPOINT)%'
    $apiKey: '%env(MYSERVICE_API_TOKEN)%'
    $useDebug: '%env(bool:MYSERVICE_API_DEBUG)%'
```

Variables d'env : un bloc `###> <Service> ###` dans `.env` (defaults non-secrets) + `.env.local` (secrets, hors git). Le flag `*_API_DEBUG` câblé sur `$useDebug` permet de dumper les requêtes Saloon en dev.

> Hors Symfony : instancie le Connector explicitement avec ses valeurs résolues (`new MyServiceAPI(baseUrl: $config['url'], apiKey: $secret)`). L'idée reste : **ne jamais laisser les defaults du constructeur faire foi en prod**.

---

## 7. Variantes courantes

### 7.1 DTO de réponse natif (objet typé plutôt que `Response` brute)

La Request implémente `createDtoFromResponse(Response): T`, l'Entrypoint retourne `$this->api->send($request)->dto()`. **Isole le mapping risqué dans une factory pure** (`Utils/<X>Factory`) qui lève l'exception domaine de la lib sur drift de schéma. La factory est une fonction pure → candidate idéale aux tests unitaires.

### 7.2 Upload de fichier (multipart) — piège majeur

Request `implements HasBody` + `use HasMultipartBody`, `defaultBody()` retourne un `array<int, MultipartValue>`.

⚠️ **La `value` d'un `MultipartValue` doit être une resource ouverte, JAMAIS un chemin en string.** Guzzle traite une string comme **contenu littéral** → tu envoies le texte `/var/www/x.jpg` au lieu des octets du fichier (→ 422 / extraction sur du charabia).

```php
new MultipartValue(
    name: 'photos',
    value: \GuzzleHttp\Psr7\Utils::tryFopen($file->getPathname(), 'r'), // resource, pas string
    filename: $file->getFilename(),
);
```

Guzzle lit alors le fichier, détecte le Content-Type, et `fclose()` la resource après envoi (pas de fuite).

### 7.3 Réponse binaire (image / PDF / ZIP)

Quand un endpoint renvoie un binaire opaque (pas du JSON) :

- **ne pas** utiliser `->dto()` ;
- la Request override `defaultHeaders()` avec le bon `Accept` (ex. `image/png`, `application/zip`) pour **neutraliser** le `AcceptsJson` hérité du Connector ;
- l'**Entrypoint** lit `$response->body()` puis matérialise le résultat — typiquement écrire un fichier dans un répertoire temporaire (`var/tmp/`) et le retourner, **l'appelant en devenant propriétaire** (responsable du cleanup) ;
- pas de factory `Utils/` (rien à mapper, binaire opaque).

Ce pattern est **orthogonal au type de body envoyé** : que tu envoies du multipart ou du JSON, la matérialisation du fichier de sortie est identique.

### 7.4 Endpoint sur un hôte alternatif (≠ base URL du Connector)

> ⚠ **Saloon v4** : la surcharge de base URL par une URL absolue dans `resolveEndpoint()` était la faille SSRF **CVE-2026-33182** (corrigée en v4.0.0). Le mécanisme `allowBaseUrlOverride` a probablement changé/durci. **Re-vérifier l'API exacte au source ou via Context7 avant de l'utiliser.** Le squelette ci-dessous reflète v3 et est à reconfirmer.

Cas : un endpoint vit sur un autre domaine que le reste de l'API (ex. un sous-service `documents.example.com` alors que le Connector pointe `api.example.com`).

⚠️ **Saloon n'appelle PAS `Request::resolveBaseUrl()`** — il joint `Connector::resolveBaseUrl()` + `Request::resolveEndpoint()`. Override `resolveBaseUrl()` sur la Request **ne fait rien**.

Solution : `resolveEndpoint()` renvoie l'**URL absolue**, ET la Request déclare le flag anti-SSRF :

```php
public ?bool $allowBaseUrlOverride = true; // le type DOIT rester ?bool (invariance de propriété)

public function resolveEndpoint(): string
{
    return 'https://documents.example.com/v1/upload'; // URL absolue
}
```

---

## 8. Pièges Saloon connus (durement gagnés)

1. **`AlwaysThrowOnErrors` + probe `ready()` → ne renvoie jamais `false`.**
   Un readiness probe écrit `ready(): bool { return $this->api->send(new GetReady())->successful(); }` **lève** l'exception domaine sur un 503 au lieu de retourner `false` : le trait appelle `$response->throw()` sur toute réponse `failed()` *avant* que `successful()` soit évalué. `successful()` n'est atteignable que sur un 2xx (où il vaut toujours `true`). → Un appelant qui veut un booléen tolérant **doit `try/catch`** l'exception.

2. **Collision de propriété `$body` avec `HasJsonBody`.**
   Une Request `use HasJsonBody` (ou `HasFormBody`, `HasMultipartBody`) **ne peut pas** déclarer une propriété promue `$body` (le trait définit déjà `$body`) → erreur fatale de composition de trait. Nomme ton payload autrement (`$payload`).

3. **`resolveBaseUrl()` de la Request est ignoré** (cf. §7.4).

4. **`value` multipart = resource, jamais string** (cf. §7.2).

5. **Le mode debug peut consommer un stream multipart.**
   `$this->debug()` (Saloon) peut lire le stream de la resource Guzzle lors du log de la requête, fermant la resource (`resource (closed)`) avant que Guzzle ne l'envoie → `InvalidArgumentException: Invalid resource type`. **Ne pas activer le debug sur une commande qui fait un upload multipart.**

6. **Les `required` d'un schéma OpenAPI sont souvent plus stricts que le runtime.**
   Ne transforme pas aveuglément chaque `required` du schéma en garde dure côté lib : tu risques de casser des flux valides que l'API accepte réellement. Calibre tes gardes sur ce que l'API **applique vraiment**, pas sur le contrat papier.

7. **Le contrat live est la source de vérité, pas un export statique.**
   Un fichier `openapi.json` posé dans le repo peut être périmé. Interroge le service qui tourne (`curl .../openapi.json`) pour connaître la version réelle et les champs exacts (y compris la casse des valeurs d'enum, qui doit matcher au caractère près).

---

## 9. Conventions transverses

- **Connector** : traits `AlwaysThrowOnErrors` + `AcceptsJson` ; `$response` pointe la Response custom ; entrypoints en `public readonly` ; `User-Agent` identifiant ton app.
- **Séparation des responsabilités** : transformation métier dans l'**Entrypoint**, jamais dans la Request.
- **Gardes d'entrée** : valider les arguments (non-vide, longueurs, format) dans l'Entrypoint **avant** tout I/O réseau (lever une `\InvalidArgumentException` ou l'exception domaine). Trimmer les entrées avant validation ET avant envoi.
- **Erreurs** : la Response custom mappe `failed()` → exception domaine, déclenchée automatiquement par `AlwaysThrowOnErrors`.
- **Enums backed** : pour les valeurs contraintes du contrat (types de service, codes pays, …), des enums backed PHP miroir du contrat. Vérifie les valeurs autorisées contre le contrat **live**.
- **Utils purs** : mappers / factories sans état dans `Utils/` (classes statiques) → testables unitairement.
- **Le fichier temp appartient à l'appelant** : une lib qui matérialise un fichier (binaire) le pose dans `var/tmp/` et le retourne ; elle ne le supprime pas. Le cleanup est la responsabilité du consommateur (try/finally).

---

## 10. Checklist « nouvelle lib »

- [ ] Connector avec `AlwaysThrowOnErrors` + `AcceptsJson`, `$response` custom, `User-Agent`.
- [ ] `AbstractEntrypoint` readonly + un Entrypoint par domaine.
- [ ] Une Request par appel HTTP, descriptive, optionnels nuls omis.
- [ ] Response custom → exception domaine typée.
- [ ] Auth : Bearer dans le Connector, ou middleware si hors-standard.
- [ ] **Binding DI explicite des env vars** (sinon defaults silencieux — piège n°1).
- [ ] Bloc `.env` / `.env.local` (secrets hors git) + flag `*_API_DEBUG`.
- [ ] Gardes d'entrée dans les Entrypoints, avant I/O.
- [ ] Multipart ? → `value` = resource. Hôte alternatif ? → URL absolue + `allowBaseUrlOverride`. Binaire ? → `Accept` override + matérialisation `File`.
- [ ] Validé E2E contre le service réel (sandbox), pas seulement en théorie.
- [ ] Aucun couplage métier : la lib ne connaît aucune entité, ne persiste rien.
```
