<!DOCTYPE html
        PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml"
        xmlns:svg="http://www.w3.org/2000/svg"
        xmlns:xlink="http://www.w3.org/1999/xlink">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no" />
    <meta name="theme-color" content="#000000" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="web-url" content="{{ $webUrl }}" />
    <meta name="api-url" content="{{ $apiUrl }}" />
    <meta name="app-url" content="{{ $appUrl }}" />
    <link href="https://use.fontawesome.com/releases/v5.0.8/css/all.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{mix('vendor/laravel-translation-manager/css/index.css') }}"/>
    <title>React App</title>
</head>
<body role="document">
<noscript>You need to enable JavaScript to run this app.</noscript>
<div class="container-fluid">
    <div id="root"></div>
</div>
<script src="{{mix('vendor/laravel-translation-manager/js/index.js')}}"></script>
</body>
</html>

