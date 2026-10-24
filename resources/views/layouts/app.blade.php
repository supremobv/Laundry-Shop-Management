<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title', 'Overview') · {{ $business->name }}</title><link rel="icon" href="{{ asset('images/logo.svg') }}" type="image/svg+xml"><link rel="stylesheet" href="{{ asset('css/app.css') }}"><script src="{{ asset('js/app.js') }}" defer></script></head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<aside class="sidebar" id="sidebar">
 <a class="brand" href="{{ route('dashboard') }}"><img src="{{ route('business.logo') }}" alt="Business logo"><span>{{ $business->name }}<small>LAUNDRY MANAGEMENT</small></span></a>
 <div class="workspace-label">WORKSPACE</div>
 <nav aria-label="Main navigation">
 @php($links = [
 ['dashboard','Overview','grid',null], ['orders.index','Orders','bag','orders.view'], ['customers.index','Customers','users','customers.manage'], ['services.index','Services & pricing','wash','catalog.manage'], ['inventory','Inventory','box','inventory.manage'], ['expenses','Expenses','wallet','expenses.manage'], ['reports','Reports','chart','reports.view'],
 ])
 @foreach($links as [$route,$label,$icon,$permission])
 @if(!$permission || auth()->user()->hasPermission($permission))
 <a class="nav-link {{ request()->routeIs($route === 'dashboard' ? 'dashboard' : explode('.',$route)[0].'*') ? 'active' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon"/><span>{{ $label }}</span>@if($route === 'orders.index')<span class="nav-dot"></span>@endif</a>
 @endif
 @endforeach
 <div class="workspace-label management">MANAGEMENT</div>
 @foreach([['staff.index','Team & access','shield','staff.manage'],['settings','Business settings','settings','settings.manage'],['audit','Audit log','receipt','audit.view']] as [$route,$label,$icon,$permission])
 @if(auth()->user()->hasPermission($permission))<a class="nav-link {{ request()->routeIs(explode('.',$route)[0].'*') ? 'active' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon"/><span>{{ $label }}</span></a>@endif
 @endforeach
 </nav>
 <div class="sidebar-bottom"><div class="help-card"><span class="help-icon"><x-icon name="spark"/></span><strong>A little help, a lot of ease.</strong><p>Your guide to a smoother laundry day.</p><a href="{{ route('help') }}">Open user guide <span>↗</span></a></div>
 <a class="profile-link" href="{{ route('profile') }}"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }}</small></span><x-icon name="settings"/></a></div>
</aside>
<div class="app-shell">
 <header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="sidebar" data-toggle-menu><x-icon name="menu"/></button><span>Workspace</span><span class="slash">/</span><strong>@yield('title','Overview')</strong></div><div class="topbar-right"><span class="local-label"><span class="online-dot"></span> {{ now()->format('D, M j, Y') }}</span><a class="icon-button" href="{{ route('help') }}" aria-label="Help"><x-icon name="help"/></a><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Sign out" title="Sign out"><x-icon name="logout"/></button></form></div></header>
 <main id="main" class="main" tabindex="-1">
 @include('partials.alerts')
 @yield('content')
 <footer class="page-footer"><span>{{ $business->name }} <span class="muted">· A fresh perspective on laundry.</span></span><span>Made for your everyday.</span></footer>
 </main>
</div>
</body></html>
