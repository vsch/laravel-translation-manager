<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="theme-color" content="#000000">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ $appUrl }}">
    {{--<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">--}}
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/x-editable/1.5.0/bootstrap3-editable/css/bootstrap-editable.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ mix('css/index.css') }}" />
{{--    <link rel="stylesheet" href="{{ mix('css/translations.css')}}">--}}
{{--    <link rel="stylesheet" href="{{ mix('css/theme.css')}}">--}}
{{--    <link rel="stylesheet" href="{{ mix('css/sidebar.css')}}">--}}
    <title>React App</title>
</head>
<body role="document">
<noscript>You need to enable JavaScript to run this app.</noscript>
<div id="root"></div>
<script src="{{mix('js/index.js')}}"></script>
</body>
</html>

