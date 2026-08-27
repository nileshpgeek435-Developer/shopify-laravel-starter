<?php

namespace App\Services\Shopify\Concerns;

use App\Exceptions\ShopifyGraphQlException;

trait AssertsShopifyUserErrors
{
    /**
     * @param  array<int, array<string, mixed>>  $userErrors
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $context
     */
    protected function throwIfUserErrors(array $userErrors, array $response, array $context = []): void
    {
        if ($userErrors === []) {
            return;
        }

        $messages = collect($userErrors)
            ->map(function (array $error): string {
                $message = (string) ($error['message'] ?? '');
                $code = isset($error['code']) ? (string) $error['code'] : '';

                if ($message === '') {
                    return (string) json_encode($error);
                }

                return $code !== '' ? "{$code}: {$message}" : $message;
            })
            ->filter()
            ->implode('; ');

        throw new ShopifyGraphQlException(
            $messages !== '' ? $messages : 'Shopify GraphQL returned userErrors.',
            errors: $userErrors,
            response: $response,
            errorCode: ShopifyGraphQlException::CODE_INVALID_INPUT,
            context: $context,
        );
    }
}
