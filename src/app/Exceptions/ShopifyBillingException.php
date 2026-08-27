<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ShopifyBillingException extends RuntimeException
{
    public const CODE_BILLING_DISABLED = 'billing_disabled';

    public const CODE_INVALID_PLAN = 'invalid_plan';

    public const CODE_MISSING_SHOP = 'missing_shop';

    public const CODE_MISSING_TOKEN = 'missing_token';

    public const CODE_NO_ACTIVE_SUBSCRIPTION = 'no_active_subscription';

    public const CODE_SHOPIFY_ERROR = 'shopify_error';

    public const CODE_UNEXPECTED = 'unexpected';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = self::CODE_UNEXPECTED,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): int
    {
        return match ($this->errorCode) {
            self::CODE_MISSING_SHOP, self::CODE_MISSING_TOKEN => 401,
            self::CODE_INVALID_PLAN, self::CODE_BILLING_DISABLED => 422,
            self::CODE_NO_ACTIVE_SUBSCRIPTION => 404,
            default => 502,
        };
    }

    public function userHint(): ?string
    {
        return match ($this->errorCode) {
            self::CODE_BILLING_DISABLED => 'Enable SHOPIFY_BILLING_ENABLED and seed plans.',
            self::CODE_INVALID_PLAN => 'Choose a valid paid plan from the billing page.',
            self::CODE_MISSING_TOKEN => 'Re-authenticate the app from Shopify Admin.',
            self::CODE_NO_ACTIVE_SUBSCRIPTION => 'There is no active paid plan to cancel.',
            self::CODE_SHOPIFY_ERROR => 'Shopify rejected the billing request. Try again or check Partners billing settings.',
            default => null,
        };
    }

    /**
     * @return array{message: string, code: string, hint: ?string}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'hint' => $this->userHint(),
        ];
    }
}
