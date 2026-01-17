@php($documentSettings = $documentSettings ?? [])

<footer class="document-footer">
    {{ $documentSettings['footer_text'] ?? '' }}
</footer>
