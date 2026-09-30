<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 520px; margin: 0 auto; padding: 20px; color: #1c2521;">
    <h1 style="color: #0b6e3f; margin: 0 0 12px;">&#9917; Ingleball</h1>
    <p style="margin: 0 0 16px;">Se registró un nuevo jugador:</p>
    <p style="margin: 0 0 4px;"><strong>{{ $user->name }}</strong></p>
    <p style="margin: 0 0 4px; color: #5f6b64;">{{ $user->username }}</p>
    @if ($user->email)
        <p style="margin: 0;">{{ $user->email }}</p>
    @endif
    @if ($user->phone)
        <p style="margin: 0;">{{ $user->phone }}</p>
    @endif
    <p style="margin: 24px 0 0;">
        <a href="{{ route('users.manage.index') }}"
           style="display: inline-block; background: #0b6e3f; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">
            Ver jugadores
        </a>
    </p>
    <p style="margin: 24px 0 0; font-size: 13px; color: #5f6b64;">Este es un mensaje automático de Ingleball.</p>
</div>