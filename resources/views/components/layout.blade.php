@props([
    'title',
    'description' => null,
    'canonical',
])
<!DOCTYPE html>
<html lang="de-DE" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/png" href="/assets/img/9166be6b-46c0-4068-8b57-2b414dd62000.png">
    <link rel="apple-touch-icon" href="/assets/img/9166be6b-46c0-4068-8b57-2b414dd62000.png">

    <link rel="alternate" hreflang="de-DE" href="https://dormed.de">
    <link rel="alternate" hreflang="x-default" href="https://dormed.de">

    <title>{{ $title }}</title>
    @if ($description)
    <meta name="description" content="{{ $description }}">
    @endif
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ $canonical }}">

    <meta property="og:title" content="{{ $title }}">
    @if ($description)
    <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Dormed med. Systeme GmbH">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    @if ($description)
    <meta name="twitter:description" content="{{ $description }}">
    @endif

    @vite(['resources/css/style.css', 'resources/css/widgets.css'])

    <style id="yuuble-theme-style">
    :root{

}

    </style>

    {{ $head ?? '' }}
  </head>
  <body>

<x-layout.header />

{{ $slot }}

<x-layout.footer />

  </body>
</html>
