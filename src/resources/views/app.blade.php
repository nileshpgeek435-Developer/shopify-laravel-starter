<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Public Client ID only (required by App Bridge CDN). Never put the API secret here. --}}
    <meta name="shopify-api-key" content="{{ \Osiset\ShopifyApp\Util::getShopifyConfig('api_key') }}">
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>

    <title inertia>{{ config('app.name') }}</title>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])

    @inertiaHead
</head>
<body class="antialiased">
    @inertia
</body>
</html>
