<meta charset="utf-8" />
<meta content="width=device-width, initial-scale=1.0" name="viewport" />

<title>{{ $title ?? config('app.name') }}</title>
<meta content="{{ $metaDescription ?? __('Discover curated jewelry with secure ecommerce-ready shopping experience.') }}"
  name="description"
>
<meta content="{{ $metaRobots ?? 'index,follow' }}" name="robots">
<link href="{{ $canonicalUrl ?? url()->current() }}" rel="canonical">

<meta content="{{ $ogTitle ?? ($title ?? config('app.name')) }}" property="og:title">
<meta
  content="{{ $ogDescription ?? ($metaDescription ?? __('Discover curated jewelry with secure ecommerce-ready shopping experience.')) }}"
  property="og:description"
>
<meta content="{{ $ogType ?? 'website' }}" property="og:type">
<meta content="{{ $ogUrl ?? ($canonicalUrl ?? url()->current()) }}" property="og:url">
@if (!empty($ogImage ?? null))
  <meta content="{{ $ogImage }}" property="og:image">
@endif

<link
  href="/favicon-96x96.png"
  rel="icon"
  sizes="96x96"
  type="image/png"
/>
<link
  href="/favicon.svg"
  rel="icon"
  type="image/svg+xml"
/>
<link href="/favicon.ico" rel="shortcut icon" />
<link
  href="/apple-touch-icon.png"
  rel="apple-touch-icon"
  sizes="180x180"
/>
<meta content="Afrodita Joyería" name="apple-mobile-web-app-title" />
<link href="/site.webmanifest" rel="manifest" />

<link href="https://fonts.bunny.net" rel="preconnect">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

@if (!empty($structuredDataJson ?? null))
  <script type="application/ld+json">{!! $structuredDataJson !!}</script>
@endif
