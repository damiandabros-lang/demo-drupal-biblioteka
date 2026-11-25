# demo-drupal-biblioteka – serwis biblioteki na Drupal 11

> **DEMO / portfolio (2026).** Projekt pokazowy Isvero Studio. Biblioteka, wydarzenia, książki i osoby są **fikcyjne**. Nie jest to strona żadnej instytucji ani wdrożenie dla klienta. Serwis wysyła `noindex` i ma baner DEMO na każdej stronie.

**Demo na żywo:** https://demo-biblioteka.isverostudio.com (noindex)

Przykład serwisu gminnej biblioteki publicznej na **Drupal 11** z własnym modułem i motywem, zgodnego z **WCAG 2.1 AA** i gotowego do wdrożenia w kontenerach (Coolify, Docker Compose).

## Co zawiera

| Element | Opis |
|---|---|
| Typy treści | Aktualność, Strona, **Wydarzenie** (termin, miejsce), **Książka** (autor, rok, dział, dostępność) |
| Moduł `isvero_demo` | strona główna z kafelkami, listy aktualności i wydarzeń (nadchodzące i minione), nowości, **katalog z wyszukiwarką** (tytuł/autor, dział, komunikat `role="status"`), mapa strony; nagłówki bezpieczeństwa i `X-Robots-Tag: noindex` |
| Motyw `isvero_biblioteka` | lekki motyw na `stable9`: link „Przejdź do treści”, widoczny fokus, kontrast AA, układ od 320 px, tabele z nagłówkami i podpisem |
| `scripts/seed.php` | konfiguracja (język polski, strefa czasowa, menu, bloki, strony błędów 403/404 po polsku, bez rejestracji kont i poczty) i dane przykładowe |
| Docker | PHP 8.3 + Apache, MariaDB 11.4, instalacja przy pierwszym starcie, hasła tylko ze zmiennych środowiskowych |
| Testy | `tests/smoke.sh` (kody HTTP, baner, `lang`, noindex, wyszukiwarka), `tests/axe.mjs` (axe-core WCAG 2.1 A/AA w 1280 px i 320 px) |

Wynik audytu automatycznego (axe-core 4.10, 10 stron × 2 szerokości ekranu): **0 naruszeń**, brak przewijania w poziomie przy 320 px, poprawny link pomijający i widoczny fokus. Pełne narzędzie audytu i raporty: repozytorium [demo-wcag-audyt](https://github.com/damiandabros-lang/demo-wcag-audyt).

## Uruchomienie lokalne (Docker)

```bash
cat > .env <<EOT
SERVICE_PASSWORD_MYSQL=$(openssl rand -hex 16)
SERVICE_PASSWORD_MYSQLROOT=$(openssl rand -hex 16)
SERVICE_PASSWORD_64_HASHSALT=$(openssl rand -hex 32)
SERVICE_PASSWORD_ADMIN=$(openssl rand -hex 12)
EOT
printf 'services:\n  web:\n    ports: ["8080:80"]\n' > docker-compose.ci.yml
docker compose -f docker-compose.yml -f docker-compose.ci.yml up -d --build
tests/smoke.sh http://localhost:8080
```

Pierwszy start instaluje Drupala (`drush site:install standard`), włącza moduł i uruchamia `scripts/seed.php`. Panel: `/user/login`, użytkownik `admin`, hasło ze zmiennej `SERVICE_PASSWORD_ADMIN`.

## Wdrożenie w Coolify

Nowa aplikacja z publicznego repozytorium, typ **Docker Compose**, plik `docker-compose.yml`. Domena tylko na usłudze `web` (port 80). Zmienne `SERVICE_PASSWORD_*` Coolify generuje sam – w repozytorium nie ma żadnych haseł. Baza nie ma publicznego portu. Limity pamięci: web 320 MB, baza 256 MB.

## Struktura

```
web/modules/custom/isvero_demo/        moduł: trasy, kontroler Biblioteka, nagłówki
web/themes/custom/isvero_biblioteka/   motyw: szablony html/page, CSS
scripts/seed.php                       konfiguracja i dane przykładowe
docker/                                settings.php (ze zmiennych), Apache, entrypoint
tests/                                 test dymny i axe-core
```

## Licencja

GPL-2.0-or-later (jak Drupal) – zob. [LICENSE](LICENSE).
