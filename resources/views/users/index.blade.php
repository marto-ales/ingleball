@extends('layouts.app')

@section('title', 'Jugadores')

@section('content')
<div class="page-head">
    <div><h1>Jugadores</h1></div>
    <a class="btn btn-primary" href="{{ route('users.manage.create') }}">+ Nuevo jugador</a>
</div>

<p class="muted">Los jugadores sin cuenta (marcados "gestionado") no pueden iniciar sesión; si se registran con su nombre, adoptan su historial.</p>

<div class="card card--info">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th>Autoevaluación</th>
                    <th class="num">Eval. org.</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            {{ $user->name }}
                            @if ($user->is_organizer) <span class="badge badge-organizer">Org</span> @endif
                            @if ($user->is_managed) <span class="badge badge-managed">Gestionado</span> @endif
                        </td>
                        <td class="muted">{{ $user->username }}</td>
                        <td>
                            @if ($user->isBanned())
                                <span class="badge badge-banned">Bloqueado</span>
                            @else
                                <span class="badge badge-active">Activo</span>
                            @endif
                        </td>
                        <td>
                            @if ($user->player?->self_eval_completed_at)
                                <span class="badge badge-active">Sí</span>
                            @else
                                <span class="muted">No</span>
                            @endif
                        </td>
                        <td>{{ $user->evaluations_received_count }}</td>
                        <td class="row gap-sm justify-end">
                            @if (! $user->is(auth()->user()))
                                <a class="btn btn-sm {{ in_array($user->id, $myEvaluated, true) ? 'btn-ghost' : 'btn-primary' }}" href="{{ route('evaluation.edit', $user) }}">Evaluar</a>
                            @endif
                            <div class="row-actions">
                                <button type="button" class="btn-icon row-actions-btn" aria-haspopup="true" aria-expanded="false" aria-label="Acciones de {{ $user->name }}" title="Acciones">
                                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                                </button>
                                <div class="row-menu" hidden>
                                    <a class="row-opt" href="{{ route('users.manage.edit', $user) }}">
                                        <span class="opt-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg></span>
                                        Editar
                                    </a>
                                    @if ($user->isBanned())
                                        <form method="POST" action="{{ route('users.manage.unblock', $user) }}">
                                            @csrf
                                            <button type="submit" class="row-opt">
                                                <span class="opt-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg></span>
                                                Desbloquear
                                            </button>
                                        </form>
                                    @elseif (! $user->is(auth()->user()))
                                        <form method="POST" action="{{ route('users.manage.block', $user) }}">
                                            @csrf
                                            <button type="submit" class="row-opt">
                                                <span class="opt-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg></span>
                                                Bloquear
                                            </button>
                                        </form>
                                    @endif
                                    @if (! $user->is(auth()->user()))
                                        <form method="POST" action="{{ route('users.manage.destroy', $user) }}" onsubmit="return confirm('¿Eliminar la cuenta de {{ addslashes($user->name) }}? Su historial se pierde.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="row-opt danger">
                                                <span class="opt-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></span>
                                                Eliminar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Sin jugadores.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection