<?php

declare(strict_types=1);

namespace AdrienGras\Umami\Requests\Auth;

use AdrienGras\Umami\Contracts\SkipsAuth;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

/**
 * `POST /api/auth/login` — public (skipAuth), carries no Bearer ({@see SkipsAuth}).
 */
class Login extends Request implements HasBody, SkipsAuth
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/api/auth/login';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'username' => $this->username,
            'password' => $this->password,
        ];
    }
}
