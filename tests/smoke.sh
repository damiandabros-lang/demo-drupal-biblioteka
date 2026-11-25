#!/bin/sh
# Test dymny działającego serwisu: kody HTTP, baner DEMO, język strony, noindex. Użycie: tests/smoke.sh http://localhost:8080
set -eu
URL="${1:-http://localhost:8080}"
blad=0
sprawdz() { # ścieżka oczekiwany_kod
  kod=$(curl -s -o /tmp/strona.html -w '%{http_code}' "$URL$1")
  if [ "$kod" != "$2" ]; then echo "BŁĄD $1: HTTP $kod (oczekiwano $2)"; blad=1; return; fi
  grep -q 'class="demo-pasek"' /tmp/strona.html || { echo "BŁĄD $1: brak banera DEMO"; blad=1; }
  grep -q '<html lang="pl"' /tmp/strona.html || { echo "BŁĄD $1: brak lang=pl"; blad=1; }
  echo "OK   $1 ($kod)"
}
for s in / /start /aktualnosci /wydarzenia /katalog "/katalog?q=kot" /nowosci /mapa-strony /deklaracja-dostepnosci /godziny-otwarcia /kontakt; do sprawdz "$s" 200; done
sprawdz /nie-ma-takiej-strony 404
curl -sI "$URL/start" | grep -qi '^x-robots-tag: noindex' || { echo "BŁĄD: brak X-Robots-Tag noindex"; blad=1; }
curl -s "$URL/katalog?q=kot" | grep -q 'Znaleziono 1 pozycję' || { echo "BŁĄD: wyszukiwarka katalogu"; blad=1; }
exit $blad
