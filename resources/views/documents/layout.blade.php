@php($documentFormat = $format ?? 'standard')
@php($documentSettings = $documentSettings ?? $hotel?->document_settings ?? [])
@php($documentLogoDisk = config('filesystems.document_logos_disk', 'public'))
@php($documentLogoPath = $documentSettings['logo_path'] ?? null)
@php($documentLogoUrl = $documentLogoPath ? Storage::disk($documentLogoDisk)->url($documentLogoPath) : null)
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Document')</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 2rem;
            background: #fff;
            color: #1f2937;
        }

        .document {
            max-width: 900px;
            margin: 0 auto;
        }

        .document-header,
        .document-footer {
            color: #1f2937;
        }

        .document-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .document-header__logo img {
            max-height: 64px;
            max-width: 180px;
            object-fit: contain;
        }

        .document-header__meta {
            flex: 1;
        }

        .document-header__title {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0 0 0.35rem;
        }

        .document-header__lines {
            font-size: 0.9rem;
            color: #4b5563;
            line-height: 1.4;
            margin: 0;
        }

        .document-header__note {
            margin-top: 0.75rem;
            font-size: 0.85rem;
            color: #6b7280;
            white-space: pre-line;
        }

        .document-body {
            min-height: 1px;
        }

        .document-footer {
            margin-top: 2rem;
            border-top: 1px solid #e5e7eb;
            padding-top: 1rem;
            font-size: 0.85rem;
            color: #6b7280;
            white-space: pre-line;
            word-break: break-word;
        }

        body.is-tsp100 {
            padding: 1rem;
        }

        body.is-tsp100 .document {
            max-width: 80mm;
        }

        @media print {
            body {
                padding: 0;
            }
        }

        @yield('document_styles')
    </style>
</head>
<body class="{{ $documentFormat === 'tsp100' ? 'is-tsp100' : '' }}">
    <div class="document">
        @include('documents.partials.header', [
            'documentSettings' => $documentSettings,
            'documentLogoUrl' => $documentLogoUrl,
            'hotel' => $hotel,
        ])

        <main class="document-body">
            @yield('content')
        </main>

        @include('documents.partials.footer', [
            'documentSettings' => $documentSettings,
        ])
    </div>

    @yield('scripts')
</body>
</html>
