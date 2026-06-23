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
- **Erreurs** : mapping via la Response custom (`UmamiApiResponse::toException()`) + cas `beep/boop` → `BotFilteredException`.
- **Enums backed** miroir du contrat **live** (casse exacte vérifiée contre l'instance qui tourne).
- **Avant commit** : `bash scripts/check.sh` vert (règle d'or 8).
