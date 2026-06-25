# Wdrożenie: 2 środowiska (prod + dev) na jednym serwerze

Tymczasowo prod i dev stoją na jednej maszynie Linux (docelowo 2 osobne serwery —
ten układ jest tak zaprojektowany, że jeden stack przenosi się na drugi serwer bez zmian).

Każde środowisko = **osobny, w pełni odseparowany stack**: własne kontenery, własna
baza MySQL, własne wolumeny i porty. Ten sam `docker-compose.yml` uruchamia oba —
różnicują je tylko pliki `./.env` (parametry compose) i `./.env.docker` (env Laravela).

| | PROD | DEV |
|---|---|---|
| katalog | `/srv/eds-prod` | `/srv/eds-dev` |
| gałąź git | `main` | `dev` |
| projekt compose | `edsprod` | `edsdev` |
| domena | `api.egurrola-app.pl` | `dev-api.egurrola-app.pl` |
| port nginx (127.0.0.1) | `8080` | `8081` |
| port MySQL (127.0.0.1) | `3307` | `3308` |
| baza danych | `app` | `app_dev` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | `local` / `true` |
| CRM | produkcyjny | pre/test |
| SMS | realne | `SMS_TEST_MODE=true` |

> Uwaga o bazach: w tym modelu **każdy stack ma własny kontener MySQL**. Prod trzyma
> dane produkcyjne w swojej bazie `app`, dev — w swojej `app_dev`. Dwie bazy, które
> dodałeś ręcznie w jednej instancji, nie są tu potrzebne (izolacja jest mocniejsza,
> a układ odpowiada przyszłemu podziałowi na 2 serwery).

---

## ⚠️ KROK 0 — Do naprawienia ZANIM prod zobaczy realne dane (bezpieczeństwo)

1. **Sekrety są w gicie.** `backend/.env.example` i `backend/env.docker.example`
   zawierają prawdziwe tokeny i hasło CRM (`CRM_PASSWORD=preStro-Zeds01`,
   `SERWERSMS_TOKEN`, `CRM_MOBILE_SYNC_TOKEN`, `CRM_PUSH_API_TOKEN`, `SMS_APP_HASH`).
   - Usuń sekrety z plików `*.example` (zostaw same nazwy kluczy z pustą wartością).
   - **Zrotuj** wszystkie te tokeny/hasła (są spalone, były w repo).
   - Prawdziwe wartości trzymaj wyłącznie w `./.env.docker` i `./.env`, które są
     w `.gitignore` (patrz krok 2.4).
2. **`APP_ENV=local` + `APP_DEBUG=true`** w przykładzie → na prod **musi** być
   `production` / `false` (inaczej stack trace z danymi leci do klienta).
3. **`CRM_BASE_URL` wskazuje na `...eds-web-pre...` (preprodukcja).** Prod z danymi
   produkcyjnymi musi celować w **produkcyjny** CRM — uzupełnij właściwy URL.

---

## KROK 1 — Wymagania na hoście (raz)

```bash
# Docker + Compose plugin
docker --version && docker compose version
# Reverse proxy + TLS (jeśli nie ma)
sudo apt update && sudo apt install -y nginx certbot python3-certbot-nginx git
```

Zatrzymaj obecny pojedynczy stack (zwolni porty 8080/3307; dane są testowe):

```bash
cd <obecny-katalog-projektu>
docker compose down                 # zostawia wolumen; dodaj -v aby skasować testowe dane
```

---

## KROK 2 — Dwa katalogi (dwa klony repo)

```bash
sudo mkdir -p /srv && sudo chown "$USER" /srv

# PROD = gałąź main
git clone <URL_REPO> /srv/eds-prod
cd /srv/eds-prod && git checkout main

# DEV = gałąź dev
git clone <URL_REPO> /srv/eds-dev
cd /srv/eds-dev && git checkout dev
```

> `docker-compose.yml` (parametryzowany) musi być na obu gałęziach. Jeśli na `dev`
> go jeszcze nie ma: `cd /srv/eds-dev && git checkout main -- docker-compose.yml DEPLOYMENT.md`.

### 2.1 PROD — plik `./.env` (parametry compose) w `/srv/eds-prod/.env`

```bash
cat > /srv/eds-prod/.env <<'EOF'
COMPOSE_PROJECT_NAME=edsprod
APP_HTTP_PORT=8080
DB_HOST_PORT=3307
MYSQL_DATABASE=app
MYSQL_USER=app
MYSQL_PASSWORD=ZMIEN_silne_haslo_prod
MYSQL_ROOT_PASSWORD=ZMIEN_silne_root_prod
EOF
```

### 2.2 PROD — plik `./.env.docker` (env Laravela) w `/srv/eds-prod/.env.docker`

```bash
cat > /srv/eds-prod/.env.docker <<'EOF'
APP_NAME=EGurrola
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.egurrola-app.pl

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=app
DB_USERNAME=app
DB_PASSWORD=ZMIEN_silne_haslo_prod      # MUSI = MYSQL_PASSWORD z ./.env

SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
MAIL_MAILER=log

# CRM — PRODUKCYJNY (uzupełnij prawdziwy URL!)
CRM_BASE_URL=https://UZUPELNIJ-PROD-CRM/API
CRM_LOGIN_ENDPOINT=/Users/login
CRM_REFRESH_ENDPOINT=/Users/login
CRM_USERNAME=ZMIEN_konto_serwisowe_prod
CRM_PASSWORD=ZMIEN_zrotowane_haslo
CRM_MOBILE_SYNC_TOKEN=ZMIEN_zrotowany_token_identyczny_w_CRM
CRM_PUSH_API_TOKEN=ZMIEN_zrotowany_token
SYNC_ENABLED=true
CRM_PAYMENT_TOKEN_URL_TEMPLATE=https://app.paynow.pl/checkout?paymentId={token}

# SMS OTP (realne)
SERWERSMS_TOKEN=ZMIEN_zrotowany
SERWERSMS_SENDER=mobile
SERWERSMS_URL=https://api2.serwersms.pl
SMS_APP_HASH=ZMIEN
SMS_TEST_MODE=false
EOF
```

### 2.3 DEV — analogicznie w `/srv/eds-dev/.env` i `/srv/eds-dev/.env.docker`

```bash
cat > /srv/eds-dev/.env <<'EOF'
COMPOSE_PROJECT_NAME=edsdev
APP_HTTP_PORT=8081
DB_HOST_PORT=3308
MYSQL_DATABASE=app_dev
MYSQL_USER=app
MYSQL_PASSWORD=ZMIEN_haslo_dev
MYSQL_ROOT_PASSWORD=ZMIEN_root_dev
EOF

cat > /srv/eds-dev/.env.docker <<'EOF'
APP_NAME="EGurrola DEV"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=https://dev-api.egurrola-app.pl

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=app_dev
DB_USERNAME=app
DB_PASSWORD=ZMIEN_haslo_dev            # MUSI = MYSQL_PASSWORD z dev ./.env

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log
MAIL_MAILER=log

# CRM — pre/test
CRM_BASE_URL=https://web-aden-eds-web-pre.azurewebsites.net/API
CRM_LOGIN_ENDPOINT=/Users/login
CRM_REFRESH_ENDPOINT=/Users/login
CRM_USERNAME=ZMIEN
CRM_PASSWORD=ZMIEN
CRM_MOBILE_SYNC_TOKEN=ZMIEN_token_dev_identyczny_w_CRM_pre
CRM_PUSH_API_TOKEN=ZMIEN_token_dev
SYNC_ENABLED=false
CRM_PAYMENT_TOKEN_URL_TEMPLATE=https://app.sandbox.paynow.pl/checkout?paymentId={token}

SERWERSMS_TOKEN=ZMIEN
SERWERSMS_SENDER=mobile
SERWERSMS_URL=https://api2.serwersms.pl
SMS_APP_HASH=ZMIEN
SMS_TEST_MODE=true
EOF
```

### 2.4 Upewnij się, że sekrety NIE trafią do gita

```bash
# w obu klonach
cd /srv/eds-prod && printf '/.env\n/.env.docker\n' >> .gitignore
cd /srv/eds-dev  && printf '/.env\n/.env.docker\n' >> .gitignore
```

---

## KROK 3 — Start środowisk

Dla **każdego** katalogu (najpierw prod, potem dev):

```bash
cd /srv/eds-prod        # następnie powtórz dla /srv/eds-dev

# 1) zbuduj obraz PHP i wystartuj (migrate odpali się automatycznie)
docker compose up -d --build

# 2) zależności (kod jest montowany, nie w obrazie)
docker compose exec php composer install --no-dev --optimize-autoloader   # na dev pomiń --no-dev

# 3) APP_KEY — env idzie przez env_file, więc generujemy i WKLEJAMY do ./.env.docker
docker compose exec php php artisan key:generate --show
#   -> skopiuj "base64:..." do APP_KEY= w ./.env.docker i zapisz

# 4) podnieś kontenery PONOWNIE, żeby złapały nowy APP_KEY z env_file
#    (uwaga: `restart` NIE przeładowuje env_file — trzeba `up -d`, które odtwarza kontenery)
docker compose up -d

# 5) storage + migracje
docker compose exec php php artisan storage:link
docker compose exec php php artisan migrate --force

# 6) PROD dodatkowo (wydajność) — i restart workerów, by wzięły cache configu:
docker compose exec php php artisan config:cache
docker compose exec php php artisan route:cache
docker compose exec php php artisan view:cache
docker compose restart queue scheduler
```

Szybka weryfikacja stacku:

```bash
docker compose ps                                  # wszystko Up; mysql healthy
curl -i http://127.0.0.1:8080/up                   # prod (dev: 8081) → 200
docker compose logs -f queue scheduler             # worker i scheduler żyją
```

---

## KROK 4 — Reverse proxy hosta + TLS (2 domeny → 2 porty)

DNS już wskazuje obie domeny na ten serwer (`api` i `dev-api` → 145.239.95.91).

```bash
# PROD vhost
sudo tee /etc/nginx/sites-available/api.egurrola-app.pl >/dev/null <<'EOF'
server {
    listen 80;
    server_name api.egurrola-app.pl;
    client_max_body_size 25m;
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

# DEV vhost
sudo tee /etc/nginx/sites-available/dev-api.egurrola-app.pl >/dev/null <<'EOF'
server {
    listen 80;
    server_name dev-api.egurrola-app.pl;
    client_max_body_size 25m;
    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/api.egurrola-app.pl     /etc/nginx/sites-enabled/
sudo ln -sf /etc/nginx/sites-available/dev-api.egurrola-app.pl /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# TLS (Let's Encrypt) dla obu domen
sudo certbot --nginx -d api.egurrola-app.pl -d dev-api.egurrola-app.pl
```

> **Laravel za proxy:** żeby `https`/payment URL-e były poprawne, ustaw zaufany proxy.
> W Laravel 11+ w `backend/bootstrap/app.php` w `->withMiddleware(...)` dodaj
> `$middleware->trustProxies(at: '*');`. Inaczej `APP_URL`/redirecty mogą lecieć po http.

---

## KROK 5 — Aktualizacja / redeploy (codzienna praca)

```bash
cd /srv/eds-prod                      # lub /srv/eds-dev
git pull
docker compose up -d --build
docker compose exec php composer install --no-dev --optimize-autoloader
docker compose exec php php artisan migrate --force
docker compose exec php php artisan config:cache route:cache view:cache   # tylko prod
docker compose restart php queue scheduler
```

## Przydatne komendy

```bash
docker compose ps                 # status (w danym katalogu)
docker compose logs -f php        # logi aplikacji
docker compose exec php sh        # shell w kontenerze
docker compose exec mysql mysql -u app -p app          # konsola DB prod (app)
docker compose down               # stop środowiska (wolumeny zostają)
docker ps                         # podgląd: edsprod-* i edsdev-* obok siebie
```

## Dlaczego to się nie zderza (izolacja)

- `COMPOSE_PROJECT_NAME` prefiksuje nazwy kontenerów, wolumenów i sieci → `edsprod-*` vs `edsdev-*`.
- Porty hosta różne (8080/3307 vs 8081/3308) i tylko na `127.0.0.1` → z internetu wejście wyłącznie przez nginx hosta (443).
- Osobne wolumeny `*_mysql_data`, `*_laravel_storage`, `*_laravel_cache` → żadnego współdzielenia danych/cache.
