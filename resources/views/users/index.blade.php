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
                    <th>Partidos</th>
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
                        <td>{{ $user->entries_count }}</td>
                        <td class="row gap-sm justify-end">
                            @if (! $user->is(auth()->user()))
                                <a class="btn btn-sm" href="{{ route('evaluation.edit', $user) }}">Calificar</a>
                            @endif
                            <a class="btn btn-sm" href="{{ route('users.manage.edit', $user) }}">Editar</a>
                            @if ($user->isBanned())
                                <form class="inline" method="POST" action="{{ route('users.manage.unblock', $user) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-ghost">Desbloquear</button>
                                </form>
                            @elseif (! $user->is(auth()->user()))
                                <form class="inline" method="POST" action="{{ route('users.manage.block', $user) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-ghost">Bloquear</button>
                                </form>
                            @endif
                            @if (! $user->is(auth()->user()))
                                <form class="inline" method="POST" action="{{ route('users.manage.destroy', $user) }}" onsubmit="return confirm('¿Eliminar la cuenta de {{ addslashes($user->name) }}? Su historial se pierde.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">Sin jugadores.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection