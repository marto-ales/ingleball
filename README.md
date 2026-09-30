# ⚽ Ingleball — fútbol de amigos

Aplicación web para organizar el partido semanal con amigos: lista de anotados,
armado de equipos balanceado, calificaciones entre jugadores, ranking e historial.

## Funcionalidades

- **Multi-grupo**: una cuenta puede pertenecer a varios grupos; el grupo activo
  se elige desde el menú superior. Cada grupo tiene su propia lista, perfiles,
  calificaciones y evaluaciones: nada se comparte entre grupos.
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
  historial; también puede bloquear o expulsar de su grupo.
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

## Comandos de consola

Gestión de grupos y cuentas desde la terminal, pensados para uso administrativo
sobre el SQLite:

```sh
# Grupos
php artisan groups:create "Nombre" [--code=ABC123] [--whatsapp=...] [--organizer=usuario]
php artisan groups:add "Grupo" usuario [--organizer]
php artisan groups:list

# Usuarios
php artisan users:create "Nombre" [--username=] [--email=] [--password=] [--managed] [--group=] [--organizer]
php artisan users:update usuario|correo [--name=] [--username=] [--email=] [--phone=] [--password=]
php artisan users:organizer usuario grupo
php artisan users:unorganizer usuario grupo
php artisan users:delete usuario|correo [--force]
php artisan users:list [--group=]

# Otros
php artisan ratings:detail
```

`users:create` sin `--username` deriva uno único del nombre. `groups:add` con
`--organizer` suma a un miembro y lo deja organizando ese grupo. El rol de
organizador es por pertenencia: no afecta otros grupos de la misma cuenta.

## Tests

```sh
php artisan optimize:clear   # necesario antes de testear
php artisan test
```

Los tests corren sobre SQLite en memoria y necesitan cachés limpias: un
`config:cache` previo arrastra `APP_ENV=production` y el middleware CSRF rechaza
todo POST con 419.

## Deploy

Entorno de producción: PHP 8.4 y SQLite, servidor
`php artisan serve --host=0.0.0.0 --port=8000` (systemd `ingleball.service`)
detrás de un nginx HTTPS en el host (configuración de front en
`deploy/nginx-front.conf`).

Cada release:

```sh
sudo ./deploy.sh
```

`deploy.sh` (correr como root): corrige el dueño de `vendor`, hace un backup del
SQLite en `storage/app/backups` antes de migrar, instala dependencias de
producción, migra, reconstruye cachés y reinicia el servicio. El `git pull` se
hace a mano (como `www-data`, vía clave SSH de deploy). Variables de entorno
opcionales: `APP_DIR`, `APP_USER` (default `www-data`), `PHP_BIN` y `SERVICE`.

## Configuración

- `config/balance.php`: pesos del puntaje combinado, tamaños de equipo (`[6,5,4]`)
  y atributos de calificación.
- `CAPTCHA_ENABLED`: activa/desactiva el captcha (tests corren con `false`).
