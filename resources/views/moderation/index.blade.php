<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Moderation — {{ config('app.name', 'mosh.dog') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Outfit:wght@800;900&family=Inter:wght@400;500;600&display=swap">

    <style>
        /* ─── Tokens ─────────────────────────────────────── */
        :root {
            --bg:           #0a0a0a;
            --surface:      #181620;
            --surface-2:    #242030;
            --ink:          #f0edf8;
            --primary:      #c49338;
            --primary-deep: #6b4f1a;
            --accent:       #8c2020;
            --muted:        #86828c;
            --border:       #28222e;

            --ease-out: cubic-bezier(0.25, 1, 0.5, 1);
            --r: 3px;
        }

        /* ─── Base ───────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-font-smoothing: antialiased; }

        body {
            background: var(--bg);
            color: var(--ink);
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 1rem;
            line-height: 1.65;
            min-height: 100svh;
        }

        a { color: inherit; text-decoration: none; }

        /* ─── Nav ────────────────────────────────────────── */
        .nav {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
            padding: 0 24px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
        }

        .nav__brand {
            font-family: 'Outfit', system-ui, sans-serif;
            font-size: 1.125rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.015em;
        }

        .nav__back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: var(--muted);
            padding: 6px 12px;
            border-radius: var(--r);
            transition: color 120ms var(--ease-out), background 120ms var(--ease-out);
        }

        .nav__back:hover { color: var(--ink); background: var(--surface-2); }

        .nav__back:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
            border-radius: var(--r);
        }

        /* ─── Main ───────────────────────────────────────── */
        .main {
            max-width: 960px;
            margin: 0 auto;
            padding: 40px 24px 80px;
        }

        /* ─── Page header ────────────────────────────────── */
        .page-hd {
            display: flex;
            align-items: baseline;
            gap: 14px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

        .page-hd__title {
            font-family: 'Outfit', system-ui, sans-serif;
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.02em;
            line-height: 1.1;
            text-wrap: balance;
        }

        .page-hd__badge {
            display: inline-flex;
            align-items: center;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: var(--r);
            background: rgba(196, 147, 56, 0.12);
            color: var(--primary);
            border: 1px solid rgba(196, 147, 56, 0.28);
            line-height: 1.5;
            position: relative;
            top: -2px;
            white-space: nowrap;
        }

        /* ─── Queue ──────────────────────────────────────── */
        .queue {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        /* ─── Row ────────────────────────────────────────── */
        .row {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 24px;
            padding: 18px 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            transition: border-color 120ms var(--ease-out);
            animation: rowIn 220ms var(--ease-out) both;
            animation-delay: var(--d, 0ms);
        }

        .row:hover { border-color: rgba(196, 147, 56, 0.22); }

        @keyframes rowIn {
            from { opacity: 0; transform: translateY(8px); }
        }

        .row__meta {
            display: flex;
            flex-direction: column;
            gap: 3px;
            min-width: 0;
        }

        .row__title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--ink);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .row__url {
            font-size: 0.8125rem;
            color: var(--primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
            transition: opacity 100ms ease;
        }

        .row__url:hover { opacity: 0.75; text-decoration: underline; }

        .row__url:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
            border-radius: 2px;
        }

        .row__foot {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 1px;
        }

        .row__type {
            font-size: 0.6875rem;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .row__sep {
            width: 2px;
            height: 2px;
            border-radius: 50%;
            background: var(--border);
            flex-shrink: 0;
        }

        .row__date {
            font-size: 0.75rem;
            color: var(--muted);
        }

        .row__actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        /* ─── Buttons ────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 8px 18px;
            border-radius: var(--r);
            border: 1px solid transparent;
            cursor: pointer;
            white-space: nowrap;
            transition:
                background 120ms var(--ease-out),
                color 120ms var(--ease-out),
                border-color 120ms var(--ease-out),
                transform 100ms var(--ease-out);
        }

        .btn:active { transform: translateY(0) !important; }

        .btn--approve {
            background: var(--primary);
            color: var(--bg);
            border-color: var(--primary);
        }

        .btn--approve:hover {
            background: var(--primary-deep);
            color: var(--ink);
            border-color: var(--primary-deep);
            transform: translateY(-1px);
        }

        .btn--approve:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 3px;
            box-shadow: 0 0 0 5px rgba(196, 147, 56, 0.18);
        }

        .btn--reject {
            background: transparent;
            color: var(--muted);
            border-color: var(--border);
        }

        .btn--reject:hover {
            color: #c45050;
            border-color: var(--accent);
            transform: translateY(-1px);
        }

        .btn--reject:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 3px;
            box-shadow: 0 0 0 5px rgba(140, 32, 32, 0.16);
        }

        /* ─── Empty state ────────────────────────────────── */
        .empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 80px 24px;
            gap: 10px;
            animation: rowIn 280ms var(--ease-out) both;
        }

        .empty__icon {
            width: 36px;
            height: 36px;
            color: var(--border);
            margin-bottom: 4px;
        }

        .empty__heading {
            font-size: 1rem;
            font-weight: 600;
            color: var(--ink);
        }

        .empty__body {
            font-size: 0.875rem;
            color: var(--muted);
            max-width: 34ch;
            line-height: 1.6;
        }

        /* ─── Responsive ─────────────────────────────────── */
        @media (max-width: 580px) {
            .nav { padding: 0 16px; }
            .main { padding: 28px 16px 64px; }

            .row {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .row__actions { justify-content: flex-end; }
        }

        /* ─── Reduced motion ─────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            .row, .empty { animation: none; }
            .btn, .nav__back, .row, .row__url { transition: none; }
        }
    </style>
</head>
<body>

<nav class="nav" aria-label="Studio navigation">
    <a href="{{ url('/dashboard') }}" class="nav__brand" aria-label="mosh.dog — back to dashboard">mosh.dog</a>
    <a href="{{ url('/studio/links') }}" class="nav__back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 12H5M12 5l-7 7 7 7"/>
        </svg>
        Studio
    </a>
</nav>

<main class="main" id="main-content">

    <header class="page-hd">
        <h1 class="page-hd__title">Moderation Queue</h1>
        @if (!$links->isEmpty())
            <span class="page-hd__badge" aria-label="{{ $links->count() }} links pending review">{{ $links->count() }} pending</span>
        @endif
    </header>

    @if ($links->isEmpty())
        <div class="empty" role="status">
            <svg class="empty__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="empty__heading">All clear</p>
            <p class="empty__body">No links are pending review. Submitted links will appear here when they arrive.</p>
        </div>
    @else
        <ul class="queue" aria-label="Links pending review">
            @foreach ($links as $link)
                <li class="row" style="--d: {{ min($loop->index, 5) * 60 }}ms">
                    <div class="row__meta">
                        <span class="row__title">{{ $link->title }}</span>
                        <a href="{{ $link->link }}"
                           class="row__url"
                           target="_blank"
                           rel="noopener noreferrer"
                           title="{{ $link->link }}">{{ $link->link }}</a>
                        <div class="row__foot" aria-label="Submitted via {{ str_replace(['_', '-'], ' ', $link->button_name) }} on {{ \Carbon\Carbon::parse($link->created_at)->format('F j, Y') }}">
                            <span class="row__type" aria-hidden="true">{{ str_replace(['_', '-'], ' ', $link->button_name) }}</span>
                            <span class="row__sep" aria-hidden="true"></span>
                            <time class="row__date" datetime="{{ $link->created_at }}" aria-hidden="true">
                                {{ \Carbon\Carbon::parse($link->created_at)->format('M j, Y') }}
                            </time>
                        </div>
                    </div>
                    <div class="row__actions">
                        <form method="POST" action="{{ route('linkstack-shared-profiles.approve', $link->id) }}">
                            @csrf
                            <button type="submit" class="btn btn--approve" aria-label="Approve {{ $link->title }}">
                                Approve
                            </button>
                        </form>
                        <form method="POST" action="{{ route('linkstack-shared-profiles.reject', $link->id) }}">
                            @csrf
                            <button type="submit" class="btn btn--reject" aria-label="Reject {{ $link->title }}">
                                Reject
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

</main>

</body>
</html>
