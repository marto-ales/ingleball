# ⚽ Ingleball — fútbol de amigos

Aplicación web para organizar el partido semanal con amigos: lista de anotados,
armado de equipos balanceado, calificaciones entre jugadores, ranking e historial.

## Funcionalidades

- **Partidos y anotados**: cada jugador se anota como *voy* o *suplente*; el
  organizador puede cerrar la lista, armar equipos 4v4, 5v5 o 6v6 de forma
  balanceada (por puntaje y preferencia de arco) e intercambiar jugadores.
- **Partidos recurrentes**: un partido marcado como recurrente se reabre solo,
  una vez por semana, con los mismos datos.
- **Finalización automática**: al pasar la hora de inicio deja de aceptar
  anotaciones y el partido pasa a *finalizado*; recién ahí se puede cargar el
  resultado (ganador, diferencia, MVP y goles).
- **Invitados globales**: invitados manejados por el organizador con historial
  propio; se identifican por teléfono y se reutilizan entre partidos.
- **Calificaciones**: autoevaluación de cada jugador y calificaciones de los
  rivales (velocidad, habilidad, pase, definición, defensa, arco y general) que
  alimentan un ranking combinado.
- **Mensaje compartible**: mensaje pre-escrito con lista y equipos para reenviar
  al grupo por WhatsApp.
- **Usuarios administrados**: el organizador puede crear jugadores sin cuenta
  (no loguean) que, si después se registran con el mismo usuario, recuperan su
  historial; también puede bloquear o eliminar usuarios.
- **Notificaciones por email**: bienvenida al registrarse, aviso a los
  organizadores de cada nuevo jugador y recordatorios de partido.
- **Captcha de imagen** en registro y login (configurable).

## Stack

- Laravel 13 · PHP ≥ 8.3
- SQLite
- CSS plano en `public/css/app.css` (sin build, sin npm/vite)
- PHPUnit para tests

**Atribuciones**: el fondo de césped es "WIKI-Grass.jpg" por Ed. Markovich, dedicada al dominio público (PD); copia local en `public/img/grass.jpg`. Fuente: https://commons.wikimedia.org/wiki/File:WIKI-Grass.jpg

## Puesta en marcha local

```sh
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

La app queda en `http://127.0.0.1:8000`.

## Tests

```sh
php artisan test
```

## Deploy

Entorno de producción: PHP 8.4 y SQLite, servidor
`php artisan serve --host=0.0.0.0 --port=8000` (systemd `ingleball.service`)
detrás de un nginx HTTPS en el host (un `ing.ingleball.service`, configuración de
front en `deploy/nginx-front.conf`).

Cada release:

```sh
sudo ./deploy.sh
```

`deploy.sh` actualiza el repositorio, corrige el dueño de `vendor`, instala
dependencias de producción, migra, reconstruye cachés y reinicia el servicio.
Variables de entorno opcionales: `APP_DIR`, `APP_USER` (default `www-data`),
`PHP_BIN` y `SERVICE`.

## Configuración

- `config/balance.php`: pesos del puntaje combinado, tamaños de equipo (`[6,5,4]`)
  y atributos de calificación.
- `CAPTCHA_ENABLED`: activa/desactiva el captcha (tests corren con `false`).
