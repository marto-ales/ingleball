<?php

/*
 * Novedades del sitio: the welcome modal shown on the dashboard while there
 * are changes an account has not acknowledged yet. Newest first: the first
 * entry is the "latest" one, and whoever acknowledges the welcome modal won't
 * be shown anything above it again.
 *
 * To announce a release add an entry at the top (never edit an old one:
 * the id is what each account remembers as already seen):
 *
 *   [
 *       'id' => '2026-11-changes',   // stable and unique
 *       'date' => '2026-11-02',      // Y-m-d, only for display
 *       'title' => 'Short headline',
 *       'changes' => [
 *           'One bullet per change.',
 *       ],
 *   ],
 */

return [
    [
        'id' => '2026-11-15-camiseta',
        'date' => '2026-11-15',
        'title' => 'El mensaje ya avisa qué camiseta lleva cada equipo',
        'changes' => [
            'El mensaje para compartir y la planilla de equipos muestran ⚪ o ⚫ en el encabezado de cada equipo: blanco es camiseta clara y negro, oscura.',
            'El color de camiseta cambia de un partido a otro y no se altera si se vuelven a armar los equipos.',
            'Con la lista cerrada, el mensaje ya no invita a anotarse al próximo partido: solo deja el enlace al partido actual.',
        ],
    ],
    [
        'id' => '2026-11-13-mvp',
        'date' => '2026-11-13',
        'title' => 'El MVP ahora lo eligen los que jugaron',
        'changes' => [
            'Ya no lo define el organizador al cargar el resultado: cada jugador que fue vota al MVP desde la página Calificar, una vez que el resultado está cargado.',
            'Gana el más votado y, si hay empate en el primer puesto, lo desempata el organizador desde la página del partido.',
            'No podés votarte a vos mismo.',
        ],
    ],
    [
        'id' => '2026-10-19-mensaje',
        'date' => '2026-10-19',
        'title' => 'El mensaje para compartir ahora muestra todos los casilleros',
        'changes' => [
            'El mensaje de un partido lista todos los puestos del formato (5v5 → 10, 6v6 → 12), numerados 01, 02, … y completados con los anotados; los que quedan libres aparecen vacíos para saber cuántos faltan.',
        ],
    ],
    [
        'id' => '2026-10-16-credenciales',
        'date' => '2026-10-16',
        'title' => 'Contraseñas y armado de equipos',
        'changes' => [
            'Los organizadores pueden darle una contraseña nueva a cualquier jugador desde Usuarios.',
            'La característica de arco ahora pesa en el armado de los equipos y se configura en Algoritmo.',
            'La tabla de Estadísticas muestra el porcentaje de partidos ganados y ordena por participación.',
        ],
    ],
    [
        'id' => '2026-09-20-puntaje',
        'date' => '2026-09-20',
        'title' => 'Mirá cómo se arma tu puntaje',
        'changes' => [
            'Pasando el mouse por la columna Puntaje ves cuánto aporta cada característica y tu rendimiento.',
            'La autoevaluación cuenta para tu puntaje solo cuando la completás.',
        ],
    ],
];
