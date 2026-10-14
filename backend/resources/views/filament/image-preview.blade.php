<div class="handi-lightbox" style="background: {{ $bg ?? '#F6F1E9' }}">
    @if ($url)
        <img src="{{ $url }}" alt="{{ $alt }}" loading="lazy">
    @else
        <p class="handi-lightbox-empty">No picture yet.</p>
    @endif
</div>
@if ($url)
    <div class="handi-lightbox-actions">
        <a href="{{ $url }}" target="_blank" rel="noopener">Open full size ↗</a>
    </div>
@endif
