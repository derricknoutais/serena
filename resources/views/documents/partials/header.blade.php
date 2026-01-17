@php($documentSettings = $documentSettings ?? [])
@php($contact = $documentSettings['contact'] ?? [])
@php($legal = $documentSettings['legal'] ?? [])
@php($displayName = $documentSettings['display_name'] ?? $hotel?->name ?? 'Hotel')
@php($lines = array_filter([
    $contact['address'] ?? null,
    $contact['phone'] ?? null,
    $contact['email'] ?? null,
    $legal['nif'] ?? null ? 'NIF: '.($legal['nif'] ?? '') : null,
    $legal['rccm'] ?? null ? 'RCCM: '.($legal['rccm'] ?? '') : null,
]))

<header class="document-header">
    <div class="document-header__meta">
        <p class="document-header__title">{{ $displayName }}</p>
        <p class="document-header__lines">
            {!! implode('<br>', array_map('e', $lines)) !!}
        </p>
        @if (! empty($documentSettings['header_text']))
            <p class="document-header__note">{{ $documentSettings['header_text'] }}</p>
        @endif
    </div>

    <div class="document-header__logo">
        @if (! empty($documentLogoUrl))
            <img src="{{ $documentLogoUrl }}" alt="Logo {{ $displayName }}">
        @endif
    </div>
</header>
