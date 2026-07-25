<!DOCTYPE html>
<html lang="de-DE" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" type="image/png" href="/assets/img/9166be6b-46c0-4068-8b57-2b414dd62000.png">
    <link rel="apple-touch-icon" href="/assets/img/9166be6b-46c0-4068-8b57-2b414dd62000.png">

    @vite(['resources/css/style.css', 'resources/css/widgets.css'])

    <style id="yuuble-theme-style">
    :root{

}

    </style>

    {{ $head }}
  </head>
  <body>

<x-layout.header />

{{ $slot }}

<x-layout.footer />

  </body>
</html>
