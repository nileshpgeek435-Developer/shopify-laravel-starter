<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ShopifyGraphQlException extends RuntimeException
{
    public const CODE_MISSING_TOKEN = 'missing_token';

    public const CODE_ACCESS_DENIED = 'access_denied';

    public const CODE_UNAUTHORIZED = 'unauthorized';

    public const CODE_RATE_LIMITED = 'rate_limited';

    public const CODE_NOT_FOUND = 'not_found';

    public const CODE_INVALID_INPUT = 'invalid_input';

    public const CODE_TRANSPORT = 'transport_error';

    public const CODE_UNEXPECTED = 'unexpected_response';

    /**
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<string, mixed>|null  $response
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly ?array $response = null,
        public readonly ?int $status = null,
        public readonly string $errorCode = self::CODE_TRANSPORT,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isAccessDenied(): bool
    {
        return $this->errorCode === self::CODE_ACCESS_DENIED
            || $this->detectAccessDeniedFromPayload();
    }

    public function isMissingToken(): bool
    {
        return $this->errorCode === self::CODE_MISSING_TOKEN;
    }

    public function httpStatus(): int
    {
        return match ($this->errorCode) {
            self::CODE_MISSING_TOKEN, self::CODE_UNAUTHORIZED => 401,
            self::CODE_ACCESS_DENIED => 403,
            self::CODE_NOT_FOUND => 404,
            self::CODE_INVALID_INPUT => 422,
            self::CODE_RATE_LIMITED => 429,
            default => 502,
        };
    }

    public function userHint(): ?string
    {
        return match ($this->errorCode) {
            self::CODE_MISSING_TOKEN => 'Reinstall or authenticate the app from Shopify Admin.',
            self::CODE_ACCESS_DENIED => 'Re-authenticate the app so required scopes are granted.',
            self::CODE_UNAUTHORIZED => 'The access token is invalid or expired. Re-authenticate the app.',
            self::CODE_RATE_LIMITED => 'Shopify rate limit hit. Try again in a moment.',
            self::CODE_NOT_FOUND => 'Shop record was not found. Install the app first.',
            self::CODE_INVALID_INPUT => 'Fix the GraphQL input (check metafield/metaobject fields) and retry.',
            default => null,
        };
    }

    /**
     * @return array{message: string, code: string, access_denied: bool, hint: ?string, status: ?int}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'access_denied' => $this->isAccessDenied(),
            'hint' => $this->userHint(),
            'status' => $this->status,
        ];
    }

    private function detectAccessDeniedFromPayload(): bool
    {
        foreach ($this->errors as $error) {
            if (($error['extensions']['code'] ?? null) === 'ACCESS_DENIED') {
                return true;
            }
        }

        return str_contains(strtolower($this->getMessage()), 'access denied');
    }
}
