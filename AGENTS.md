# Ingleball

App de fútbol de amigos: multi-grupo, anotados, armado balanceado de equipos, ranking e historial.
Laravel 13 · PHP ≥ 8.3 · SQLite · Blade, sin build de front. El `README.md` explica el producto, la
puesta en marcha y los comandos de consola (`groups:*`, `users:*`, `ratings:detail`).

## Comandos

- Tests: `php artisan optimize:clear && php artisan test` (o `composer test`, que ya corre
  `config:clear` antes). **Nunca** testear arrancando de cachés: un `config:cache` deja
  `APP_ENV=production` y el middleware CSRF devuelve 419 en todos los POST.
- Suite rápida: `XDEBUG_MODE=off php artisan test --no-coverage`.
- Un solo test: `php artisan test --filter=NombreTest` o `--filter=nombre_del_metodo`.
- Estilo: `./vendor/bin/pint` (no hay script de lint en composer); `./vendor/bin/pint <archivo>` para uno.
- El CSS es estático en `public/css/app.css`, linkueado con `asset()` en `resources/views/layouts/app.blade.php`.
  `npm`/`vite` no participan (solo `welcome.blade.php` usa `@vite`): no hay que correr `npm run build` para cambios de UI.

## Arquitectura

- Controladores finos; el dominio vive en `app/Services`: `Scorer` (perfil/puntaje y blending
  autoevaluación + organizadores), `TeamBalancer` (algoritmo puro de armado), `TeamService`
  (participantes, persistencia y reordenamiento de la planilla), `AlgorithmSettings` (config por grupo),
  `StatsService`, `MessagingService`, `GroupMembershipService`.
- Todo es **por grupo**: `app/Support/ActiveGroup.php` resuelve el grupo activo desde la sesión.
  Usá `Scorer::forGroup($id)`, `AlgorithmSettings::forGroup($id)`, `Group::membersQuery($groupId)`
  o `where('group_id', ...)`. En tests el grupo default es `UserFactory::defaultGroupId()`.
- La planilla de `matches/show` se reordena al vuelo (`TeamService::arrangeRows`, en memoria); no
  reescribe los `position` guardados. `TeamBalancer::balance()` sí debe llamar a `orderByRanking()`
  directo: hacerlo pasar por el reordenamiento de lectura cambia el desempate.
- La config del algoritmo por grupo se guarda en la tabla `settings` (key `algorithm`), cacheada 1 día;
  `AlgorithmSettings::update()` la invalida.

## Características y pesos (`config/balance.php`)

`config/balance.php` es la fuente de verdad. Agregar o quitar una característica obliga a tocar todo
esto en conjunto:

1. `config/balance.php`: `attributes` y `attribute_weights` (los pesos suman 1).
2. `app/Services/AlgorithmSettings.php`: `RANKED_WEIGHTS` (un peso por posición del orden; la pantalla
   Algoritmo deriva de ahí). Un atributo agregado entra al final del orden guardado vía `normalize()`.
3. Mapas de labels inline en Blade (se duplican, no hay composer compartido):
   `algorithm/index.blade.php`, `matches/show.blade.php` (`$attrKeys` + `$attrLabels`),
   `stats/index.blade.php`, `stats/show.blade.php`, `profile/edit.blade.php`, `evaluation/edit.blade.php`.
4. `PATCH /algorithm` valida `order` con `size:count(config('balance.attributes'))`: los tests deben
   mandar **todos** los atributos.

`Guest` no tiene columna `goalkeeping`: se lee neutral (5).

## Testing

- SQLite `:memory:` + `RefreshDatabase`, sin servicios externos; `CAPTCHA_ENABLED=false`.
- Armá los datos con factories: `UserFactory` crea la membresía y un `Player` por grupo; no hagas
  membresías/perfiles a mano.

## Deploy

Producción: Proxmox LXC, Debian 13, PHP 8.4, SQLite, `php artisan serve` (systemd `ingleball.service`)
detrás de un nginx en el host (`deploy/nginx-front.conf`). Cada release, como root y **solo si el
usuario lo pide**:

```sh
sudo ./deploy.sh
```

`deploy.sh` corrige el dueño de `vendor`, hace un backup del SQLite en `storage/app/backups` antes de
migrar, `composer install --no-dev --optimize-autoloader`, `migrate --force`, `config:cache` /
`route:cache` / `view:cache` y reinicia la unidad. El `git pull --ff-only` es manual, como `www-data`
(clave SSH de deploy). No hay build de npm/vite.

Setup inicial del contenedor:

```sh
apt install php8.4-cli php8.4-sqlite3 php8.4-mbstring php8.4-xml php8.4-curl php8.4-fpm unzip git composer
git clone <repo> /srv/ingleball && cd /srv/ingleball
cp .env.example .env && php8.4 artisan key:generate   # .env: APP_ENV=production, APP_DEBUG=false, MAIL_MAILER=smtp...
touch database/database.sqlite
chown -R www-data:www-data storage bootstrap/cache database
php8.4 artisan migrate --force
cp deploy/ingleball.service /etc/systemd/system/ && systemctl daemon-reload && systemctl enable --now ingleball.service
```
