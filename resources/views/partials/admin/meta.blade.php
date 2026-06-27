<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ isset($title) && $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

<meta name="description" content="{{ $description ?? config('app.name') }}">
<meta property="og:title" content="{{ isset($title) && $title ? $title.' — '.config('app.name') : config('app.name') }}">
<meta property="og:description" content="{{ $description ?? config('app.name') }}">
<meta property="og:type" content="website">
