<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 520px; margin: 0 auto; padding: 20px; color: #1c2521;">
    <h1 style="color: #0b6e3f; margin: 0 0 12px;">&#9917; Ingleball</h1>
    <p style="margin: 0 0 16px;">Recordatorio de partido:</p>
    <h2 style="margin: 0 0 8px; font-size: 20px;">{{ $match->title }}</h2>
    <p style="font-size: 16px; margin: 6px 0;">&#128197; {{ ucfirst($match->played_at->isoFormat('dddd, D [de] MMMM [de] YYYY · HH:mm')) }}</p>
    @if ($match->venue)
        <p style="margin: 6px 0;">&#128205; {{ $match->venue }}</p>
    @endif
    <p style="margin: 24px 0 0;">
        <a href="{{ route('matches.show', $match) }}"
           style="display: inline-block; background: #0b6e3f; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">
            Ver partido
        </a>
    </p>
    <p style="margin: 24px 0 0; font-size: 13px; color: #5f6b64;">Este es un recordatorio automático de Ingleball.</p>
</div>
