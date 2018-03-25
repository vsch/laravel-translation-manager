<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="theme-color" content="#000000">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-url" content="{{ $appUrl }}">
    <link href="https://use.fontawesome.com/releases/v5.0.8/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ mix('css/index.css') }}"/>
    <title>React App</title>
</head>
<body role="document">
<noscript>You need to enable JavaScript to run this app.</noscript>
<div class="container-fluid">
    <div id="root"></div>
</div>
<script>
    var URL_YANDEX_TRANSLATOR_KEY = '<?= action($controller . '@postYandexKey') ?>';
    var URL_TRANSLATOR_FILTERS = '<?= action($controller . '@getTransFilters') ?>';
    var MARKDOWN_KEY_SUFFIX = '<?= $markdownKeySuffix ?>';
    
    // TODO: review translation.js to make sure these are not used 
    //{{--var PRIMARY_LOCALE = '{{$primaryLocale}}';--}}
    //{{--var CURRENT_LOCALE = '{{$currentLocale}}';--}}
    //{{--var TRANSLATING_LOCALE = '{{$translatingLocale}}';--}}
    //{{--var URL_TRANSLATOR_GROUP = '<?= action($controller . '@getView') ?>/';--}}
    //{{--var URL_TRANSLATOR_ALL = '<?= action($controller . '@getIndex') ?>';--}}
</script>
<script src="{{mix('js/index.js')}}"></script>
</body>
</html>

