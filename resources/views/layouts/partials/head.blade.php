<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>@yield('title', $title ?? config('app.name'))</title>
<meta name="description" content="@yield('meta_description', \App\Models\Setting::get('site_tagline', 'Cloud Android phones for multi-account management, social media automation and app testing. Deploy in seconds, run 24/7.'))">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
