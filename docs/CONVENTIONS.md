# Conventions de code

Squelettes et patterns récurrents du projet. À consulter avant de créer un nouveau type de fichier (Connector, Entrypoint, Request, Response, Exception, Enum, DTO).

Si tu découvres un pattern récurrent : documente-le ici.

> **Source canonique des squelettes** : `docs/SALOON_LIBRARY_DESIGN.md` §4 (Connector,
> AbstractEntrypoint, Entrypoint, Request, Response/Exception) — le pattern transport-only
> complet y est, ne pas le dupliquer ici. Ce fichier ne garde qu'un squelette représentatif
> et les **règles tacites** propres au projet.

---

## Request (POST body JSON) — squelette représentatif

```php
<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Foo;

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
        private readonly ?int $size = null, // optionnel nul → omis du body
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/foo';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        $payload = ['name' => $this->name]; // ⚠ jamais $body (collision trait HasJsonBody)

        if (null !== $this->size) {
            $payload['size'] = $this->size;
        }

        return $payload;
    }
}
```

### Règles tacites
- **Langue** : docs / commits / commentaires de spec en **français** ; code + phpdoc publique en **anglais** (Packagist).
- **Saloon `^4.0`** (jamais v3 : CVE, cf. QUIRKS). `declare(strict_types=1)` partout.
- **Transport-only** : la transformation/normalisation/garde d'entrée vit dans l'**Entrypoint**,
  jamais dans la Request (qui est « stupide ») ni dans le Connector (infra).
- **Optionnels nuls omis** du body/query (pas de `null` envoyé).
- **Payload nommé `$payload`** (jamais `$body`) dès qu'un trait `HasJsonBody`/`HasFormBody`/`HasMultipartBody` est utilisé.
- **Gardes d'entrée** (non-vide, longueurs, format) dans l'Entrypoint **avant** tout I/O ; trim avant validation ET avant envoi.
- **Tracking** : Requests marquées d'un marqueur d'interface (ex. `SkipsAuth`) → exclues de l'injection du Bearer.
- **Erreurs** : mapping via la Response custom `UmamiApiResponse` — override `failed()` + `createException()` (mécanique **v4**, cf. ci-dessous) + cas `beep/boop` → `BotFilteredException`.
- **Enums backed** miroir du contrat **live** (casse exacte vérifiée contre l'instance qui tourne).
- **Avant commit** : `bash scripts/check.sh` vert (règle d'or 8).

---

## Response custom — mapping d'erreur Saloon **v4** (vérifié au source)

⚠ La doc du pattern (`SALOON_LIBRARY_DESIGN.md` §4.4) décrit le mécanisme **v3** (override
`toException()` avec `if ($this->failed())`). En **v4**, `AlwaysThrowOnErrors` appelle
`$response->throw()`, qui consulte **`shouldThrowRequestException()`** (défaut Connector/Request
dans `ManagesExceptions` = `$response->failed()`), PAS `toException()` directement. Pour
requalifier un **2xx** (le bot `beep/boop`), il faut donc override `failed()`. Le mapping du type
d'exception se fait dans **`createException()`** (protected). Squelette livré :

```php
class UmamiApiResponse extends Response
{
    public function isBotFiltered(): bool
    {
        if ($this->status() !== 200) return false;
        $body = $this->body();
        if (!str_contains($body, 'beep')) return false;          // garde avant decode
        $decoded = json_decode($body, true);
        return is_array($decoded) && ($decoded['beep'] ?? null) === 'boop';
    }

    public function failed(): bool                                 // pilote shouldThrow → throw
    {
        return parent::failed() || $this->isBotFiltered();
    }

    protected function createException(): Throwable                // appelé par toException()
    {
        $previous = $this->getSenderException();
        if ($this->isBotFiltered()) {
            return new BotFilteredException($this, 'Hit dropped by Umami bot filter (beep/boop).', 0, $previous);
        }
        return new UmamiApiException($this, null, 0, $previous);   // message auto (status + body)
    }
}
```

- **Exceptions** : `UmamiApiException extends RequestException` (PHP pur, **pas** de
  `HttpExceptionInterface` Symfony) ; helpers `getStatusCode()` (= `getStatus()`) et
  `errorCode()` (lit `error.code` du body `{"error":{message,code,status}}`).
  `BotFilteredException extends UmamiApiException` (sous-type).
- Constructeur `RequestException(Response $r, ?string $message = null, int $code = 0, ?Throwable $previous = null)` — message auto-généré si `null`.

## Connector — auth deux régimes (middleware Bearer)

Bearer injecté par middleware `onRequest`, **exclu** des Requests `instanceof SkipsAuth` (tracking) :

```php
if (null !== $this->apiToken) {
    $this->middleware()->onRequest(function (PendingRequest $p): void {
        if ($p->getRequest() instanceof SkipsAuth) return;
        $p->headers()->add('Authorization', 'Bearer ' . $this->apiToken);
    });
}
```

`baseUrl` **requis** (pas de défaut bidon — PHP pur, instanciation explicite). `$response = UmamiApiResponse::class`.

## Tests unitaires — mock Saloon v4

`MockResponse::make($body, $status, $headers)` (body array → JSON). `$api->withMockClient(new MockClient([$mock]))`.
Inspecter la requête résolue (headers, auth) via `$response->getPendingRequest()->headers()->all()`.
Request de test = classe anonyme `new class () extends Request { ... }` (cs-fixer impose les `()`).
