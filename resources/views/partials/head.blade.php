<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>
<meta name="description" content="{{ $metaDescription ?? __('Discover curated jewelry with secure ecommerce-ready shopping experience.') }}">
<meta name="robots" content="{{ $metaRobots ?? 'index,follow' }}">
<link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">

<meta property="og:title" content="{{ $ogTitle ?? ($title ?? config('app.name')) }}">
<meta property="og:description" content="{{ $ogDescription ?? ($metaDescription ?? __('Discover curated jewelry with secure ecommerce-ready shopping experience.')) }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:url" content="{{ $ogUrl ?? ($canonicalUrl ?? url()->current()) }}">
@if (! empty($ogImage ?? null))
    <meta property="og:image" content="{{ $ogImage }}">
@endif

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

@if (! empty($structuredDataJson ?? null))
    <script type="application/ld+json">{!! $structuredDataJson !!}</script>
@endif
