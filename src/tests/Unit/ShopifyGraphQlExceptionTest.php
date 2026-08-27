<?php

namespace Tests\Unit;

use App\Exceptions\ShopifyGraphQlException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ShopifyGraphQlExceptionTest extends TestCase
{
    #[Test]
    public function it_maps_error_codes_to_http_statuses_and_hints(): void
    {
        $denied = new ShopifyGraphQlException(
            'Access denied for products field.',
            errors: [['message' => 'Access denied', 'extensions' => ['code' => 'ACCESS_DENIED']]],
            errorCode: ShopifyGraphQlException::CODE_ACCESS_DENIED,
        );

        $this->assertTrue($denied->isAccessDenied());
        $this->assertSame(403, $denied->httpStatus());
        $this->assertNotNull($denied->userHint());
        $this->assertSame('access_denied', $denied->toArray()['code']);

        $missing = new ShopifyGraphQlException(
            'Shop has no offline access token.',
            errorCode: ShopifyGraphQlException::CODE_MISSING_TOKEN,
        );

        $this->assertTrue($missing->isMissingToken());
        $this->assertSame(401, $missing->httpStatus());

        $notFound = new ShopifyGraphQlException(
            'Shop not found: demo.myshopify.com',
            errorCode: ShopifyGraphQlException::CODE_NOT_FOUND,
        );

        $this->assertSame(404, $notFound->httpStatus());

        $invalid = new ShopifyGraphQlException(
            'Key is invalid',
            errorCode: ShopifyGraphQlException::CODE_INVALID_INPUT,
        );

        $this->assertSame(422, $invalid->httpStatus());
        $this->assertNotNull($invalid->userHint());
        $this->assertSame('invalid_input', $invalid->toArray()['code']);
    }
}
