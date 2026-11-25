<?php

/**
 * @file
 * Dane demonstracyjne biblioteki (fikcyjne). Uruchomienie: drush php:script scripts/seed.php
 *
 * Skrypt jest idempotentny w zakresie konfiguracji; treści tworzy tylko, gdy nie ma żadnych książek.
 */

use Drupal\block\Entity\Block;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\system\Entity\Menu;

$cf = \Drupal::configFactory();

// Bez połączeń z drupal.org (aktualizacje, ogłoszenia) – demo działa offline.
$mh = \Drupal::moduleHandler();
$doUsuniecia = array_values(array_filter(['update', 'announcements_feed'], [$mh, 'moduleExists']));
if ($doUsuniecia) {
  \Drupal::service('module_installer')->uninstall($doUsuniecia);
}

// Język polski jako domyślny.
if (!ConfigurableLanguage::load('pl')) {
  ConfigurableLanguage::createFromLangcode('pl')->save();
}
$cf->getEditable('system.site')
  ->set('default_langcode', 'pl')
  ->set('name', 'Gminna Biblioteka Publiczna w Przykładowie – DEMO')
  ->set('slogan', 'Projekt pokazowy Isvero Studio (2026). Fikcyjna biblioteka i dane.')
  ->set('mail', 'demo@example.invalid')
  ->set('page.front', '/start')
  ->save();
// Polski bez prefiksu w adresach (/katalog zamiast /pl/katalog).
$cf->getEditable('language.negotiation')->set('url.prefixes', ['pl' => '', 'en' => 'en'])->save();
$cf->getEditable('system.date')->set('timezone.default', 'Europe/Warsaw')->set('first_day', 1)->set('country.default', 'PL')->save();
// Brak rejestracji kont i wysyłki poczty.
$cf->getEditable('user.settings')->set('register', 'admin_only')->save();
$cf->getEditable('system.mail')->set('interface.default', 'test_mail_collector')->save();
$cf->getEditable('system.performance')->set('css.preprocess', TRUE)->set('js.preprocess', TRUE)->set('cache.page.max_age', 300)->save();

// Motyw.
\Drupal::service('theme_installer')->install(['isvero_biblioteka', 'claro']);
$cf->getEditable('system.theme')->set('default', 'isvero_biblioteka')->set('admin', 'claro')->save();
$cf->getEditable('node.settings')->set('use_admin_theme', TRUE)->save();

// Typy treści.
function isv_typ(string $id, string $nazwa, string $opis): void {
  if (!NodeType::load($id)) {
    NodeType::create(['type' => $id, 'name' => $nazwa, 'description' => $opis, 'new_revision' => TRUE, 'display_submitted' => FALSE])->save();
  }
  isv_pole($id, 'body', 'text_with_summary', 'Opis');
}
function isv_pole(string $typ, string $pole, string $rodzaj, string $etykieta, array $ustawienia = [], array $ustawieniaPola = []): void {
  if (!FieldStorageConfig::loadByName('node', $pole)) {
    FieldStorageConfig::create(['field_name' => $pole, 'entity_type' => 'node', 'type' => $rodzaj, 'settings' => $ustawienia])->save();
  }
  if (!FieldConfig::loadByName('node', $typ, $pole)) {
    FieldConfig::create(['field_name' => $pole, 'entity_type' => 'node', 'bundle' => $typ, 'label' => $etykieta, 'settings' => $ustawieniaPola])->save();
    $repo = \Drupal::service('entity_display.repository');
    $repo->getFormDisplay('node', $typ)->setComponent($pole)->save();
    $repo->getViewDisplay('node', $typ)->setComponent($pole, ['label' => $pole === 'body' ? 'hidden' : 'inline'])->save();
  }
}
isv_typ('article', 'Aktualność', 'Wiadomość na stronie biblioteki.');
isv_typ('page', 'Strona', 'Stała strona informacyjna.');
isv_typ('wydarzenie', 'Wydarzenie', 'Spotkanie, warsztaty lub inne wydarzenie w bibliotece.');
isv_pole('wydarzenie', 'field_termin', 'datetime', 'Termin', ['datetime_type' => 'datetime']);
isv_pole('wydarzenie', 'field_miejsce', 'string', 'Miejsce');
isv_typ('ksiazka', 'Książka', 'Pozycja w katalogu zbiorów (dane fikcyjne).');
isv_pole('ksiazka', 'field_autor', 'string', 'Autor');
isv_pole('ksiazka', 'field_rok', 'integer', 'Rok wydania');
isv_pole('ksiazka', 'field_kategoria', 'string', 'Kategoria');
isv_pole('ksiazka', 'field_dostepnosc', 'list_string', 'Dostępność', ['allowed_values' => ['dostepna' => 'Dostępna', 'wypozyczona' => 'Wypożyczona']]);

// Bez komentarzy w aktualnościach (formularz komentarza nie jest potrzebny w demo).
if ($c = FieldConfig::loadByName('node', 'article', 'comment')) {
  $c->delete();
}

// Link „Home” z profilu standard zastępujemy własnym menu.
\Drupal::service('plugin.manager.menu.link')->updateDefinition('standard.front_page', ['enabled' => FALSE]);

// Menu stopki.
if (!Menu::load('footer')) {
  Menu::create(['id' => 'footer', 'label' => 'Stopka'])->save();
}

// Bloki motywu.
$bloki = [
  'isv_menu_glowne' => ['system_menu_block:main', 'nawigacja', ['level' => 1, 'depth' => 1, 'expand_all_items' => FALSE]],
  'isv_menu_stopka' => ['system_menu_block:footer', 'stopka', ['level' => 1, 'depth' => 1, 'expand_all_items' => FALSE]],
  'isv_komunikaty' => ['system_messages_block', 'tresc', []],
  'isv_tytul' => ['page_title_block', 'tresc', []],
  'isv_zakladki' => ['local_tasks_block', 'tresc', []],
  'isv_tresc' => ['system_main_block', 'tresc', []],
];
// Bloki skopiowane automatycznie z poprzedniego motywu usuwamy – motyw ma tylko własne.
foreach (\Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => 'isvero_biblioteka']) as $b) {
  if (!isset($bloki[$b->id()])) {
    $b->delete();
  }
}
$waga = 0;
foreach ($bloki as $id => [$plugin, $region, $ust]) {
  if (!Block::load($id)) {
    Block::create(['id' => $id, 'theme' => 'isvero_biblioteka', 'region' => $region, 'plugin' => $plugin, 'weight' => $waga, 'settings' => ['label_display' => '0'] + $ust])->save();
  }
  $waga++;
}

// Treści tworzymy tylko raz.
if (\Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'ksiazka')->count()->execute() > 0) {
  echo "Treści już istnieją – pomijam.\n";
  return;
}

function isv_wezel(array $w): Node {
  $n = Node::create($w + ['status' => 1, 'uid' => 1, 'langcode' => 'pl']);
  $n->save();
  return $n;
}

$strony = [
  ['O bibliotece', '/o-bibliotece', '<p>Gminna Biblioteka Publiczna w Przykładowie to <strong>fikcyjna</strong> instytucja stworzona na potrzeby projektu pokazowego. Ma wypożyczalnię dla dorosłych i dzieci, czytelnię z dostępem do internetu oraz filię w Nowej Wsi.</p><p>Wszystkie dane w serwisie, w tym nazwiska, adresy i numery telefonów, są zmyślone.</p>'],
  ['Godziny otwarcia', '/godziny-otwarcia', '<h2>Wypożyczalnia i czytelnia</h2><table><caption>Godziny otwarcia biblioteki głównej</caption><thead><tr><th scope="col">Dzień</th><th scope="col">Godziny</th></tr></thead><tbody><tr><th scope="row">Poniedziałek</th><td>10:00–18:00</td></tr><tr><th scope="row">Wtorek</th><td>8:00–16:00</td></tr><tr><th scope="row">Środa</th><td>10:00–18:00</td></tr><tr><th scope="row">Czwartek</th><td>8:00–16:00</td></tr><tr><th scope="row">Piątek</th><td>8:00–15:00</td></tr><tr><th scope="row">Sobota</th><td>9:00–13:00 (pierwsza sobota miesiąca)</td></tr></tbody></table><h2>Filia w Nowej Wsi</h2><p>Wtorek i czwartek 12:00–18:00.</p>'],
  ['Zapisy i regulamin', '/zapisy-i-regulamin', '<p>Zapisać się może każdy, kto okaże dokument tożsamości. Za osoby niepełnoletnie zobowiązanie podpisuje rodzic lub opiekun. Korzystanie z biblioteki jest bezpłatne.</p><ul><li>Jednorazowo można wypożyczyć do 5 książek na 30 dni.</li><li>Wypożyczenie można przedłużyć telefonicznie lub w bibliotece.</li><li>Książki z czytelni udostępniamy na miejscu.</li></ul><p>To przykładowy regulamin demonstracyjny.</p>'],
  ['Kontakt', '/kontakt', '<p>Gminna Biblioteka Publiczna w Przykładowie (dane fikcyjne)<br>ul. Przykładowa 1, 00-000 Przykładowo</p><p>Telefon: <a href="tel:+48000000000">+48 000 000 000</a><br>E-mail: <a href="mailto:biblioteka@example.invalid">biblioteka@example.invalid</a></p><p>Wejście do budynku jest bez schodów, a przy wejściu jest miejsce postojowe dla osób z niepełnosprawnościami.</p>'],
  ['Deklaracja dostępności', '/deklaracja-dostepnosci', '<p><strong>Przykładowa deklaracja dostępności serwisu demonstracyjnego.</strong> Gminna Biblioteka Publiczna w Przykładowie zobowiązuje się zapewnić dostępność swojej strony internetowej zgodnie z ustawą z dnia 4 kwietnia 2019 r. o dostępności cyfrowej stron internetowych i aplikacji mobilnych podmiotów publicznych.</p><h2>Stan dostępności cyfrowej</h2><p>Strona jest zgodna z WCAG 2.1 na poziomie AA. Zgodność sprawdzono automatycznie (axe-core) i ręcznie (klawiatura, powiększenie 200%, ekran 320 px).</p><h2>Przygotowanie deklaracji</h2><p>Deklarację sporządzono 25 listopada 2026 r. na podstawie samooceny.</p><h2>Informacje zwrotne i dane kontaktowe</h2><p>Uwagi prosimy kierować na adres <a href="mailto:dostepnosc@example.invalid">dostepnosc@example.invalid</a> (adres fikcyjny).</p><h2>Dostępność architektoniczna</h2><p>Budynek ma wejście bez schodów i toaletę dostosowaną do potrzeb osób na wózkach. Można wejść z psem asystującym.</p>'],
  ['Nie znaleziono strony', '/nie-znaleziono', '<p>Strona, której szukasz, nie istnieje albo została przeniesiona.</p><ul><li><a href="/start">Strona główna</a></li><li><a href="/katalog">Katalog zbiorów</a></li><li><a href="/mapa-strony">Mapa strony</a></li></ul>'],
  ['Brak dostępu', '/brak-dostepu', '<p>Ta część serwisu jest dostępna tylko dla pracowników biblioteki.</p><p><a href="/start">Wróć na stronę główną</a></p>'],
];
$id = [];
foreach ($strony as [$t, $alias, $tresc]) {
  $id[$alias] = isv_wezel(['type' => 'page', 'title' => $t, 'body' => ['value' => $tresc, 'format' => 'full_html'], 'path' => ['alias' => $alias]])->id();
}

$teraz = \Drupal::time()->getRequestTime();
$aktualnosci = [
  ['Nowe godziny otwarcia w grudniu', 'W okresie świątecznym biblioteka będzie czynna krócej. Szczegóły na stronie z godzinami otwarcia.', 2],
  ['Ferie zimowe w bibliotece – zapisy', 'Zapraszamy dzieci w wieku 7–12 lat na warsztaty plastyczne i czytelnicze. Liczba miejsc ograniczona.', 6],
  ['Kiermasz książek używanych', 'Książki wycofane ze zbiorów można zabrać za symboliczną cegiełkę na rzecz zakupu nowości.', 11],
  ['Wyniki konkursu „Moja ulubiona książka”', 'Dziękujemy wszystkim uczestnikom. Prace laureatów można oglądać w czytelni.', 18],
  ['Nowy zestaw audiobooków', 'Do zbiorów trafiło 40 audiobooków dla dorosłych i dzieci, także dla osób słabowidzących.', 25],
];
foreach ($aktualnosci as [$t, $tresc, $dni]) {
  isv_wezel(['type' => 'article', 'title' => $t, 'created' => $teraz - $dni * 86400, 'body' => ['value' => '<p>' . $tresc . '</p><p>To wiadomość demonstracyjna – biblioteka i wydarzenia są fikcyjne.</p>', 'summary' => $tresc, 'format' => 'full_html']]);
}

$wydarzenia = [
  ['Spotkanie autorskie: kryminał z małego miasta', 4, '17:00', 'Czytelnia, biblioteka główna'],
  ['Klub Czytelniczy Dyskusyjny', 9, '18:00', 'Sala na piętrze'],
  ['Warsztaty „Zrób własną zakładkę”', 14, '10:00', 'Oddział dla dzieci'],
  ['Kurs: bankowość internetowa bez stresu (dla seniorów)', 20, '11:00', 'Czytelnia komputerowa'],
  ['Głośne czytanie dla przedszkolaków', 27, '9:30', 'Filia w Nowej Wsi'],
  ['Wieczór gier planszowych', -6, '17:00', 'Czytelnia'],
  ['Noc bibliotek – zwiedzanie zbiorów', -20, '19:00', 'Biblioteka główna'],
];
foreach ($wydarzenia as [$t, $dni, $godz, $miejsce]) {
  $lokalnie = new \DateTime('@' . ($teraz + $dni * 86400));
  $lokalnie->setTimezone(new \DateTimeZone('Europe/Warsaw'));
  [$h, $m] = explode(':', $godz);
  $lokalnie->setTime((int) $h, (int) $m);
  $lokalnie->setTimezone(new \DateTimeZone('UTC'));
  isv_wezel(['type' => 'wydarzenie', 'title' => $t, 'field_termin' => $lokalnie->format('Y-m-d\TH:i:s'), 'field_miejsce' => $miejsce, 'body' => ['value' => '<p>Wstęp wolny. Wydarzenie demonstracyjne.</p>', 'format' => 'full_html']]);
}

$ksiazki = [
  ['Cisza nad stawem', 'Anna Przykładowa', 2025, 'Kryminał', 'dostepna'],
  ['Mapa wiatrów', 'Jan Testowy', 2024, 'Powieść', 'wypozyczona'],
  ['Ogród po deszczu', 'Maria Zmyślona', 2026, 'Powieść', 'dostepna'],
  ['Historia jednej ulicy', 'Piotr Fikcyjny', 2023, 'Historia', 'dostepna'],
  ['Kot, który liczył gwiazdy', 'Ewa Wymyślona', 2025, 'Dla dzieci', 'dostepna'],
  ['Pociąg do Nowej Wsi', 'Tomasz Przykładowy', 2022, 'Reportaż', 'wypozyczona'],
  ['Programowanie dla ciekawych', 'Karol Demo', 2026, 'Popularnonaukowe', 'dostepna'],
  ['Zielnik babci Heli', 'Halina Testowa', 2021, 'Poradnik', 'dostepna'],
  ['Szyfr z biblioteki', 'Anna Przykładowa', 2026, 'Kryminał', 'wypozyczona'],
  ['Wyprawa na Księżyc', 'Ewa Wymyślona', 2024, 'Dla dzieci', 'dostepna'],
  ['Las w czterech porach roku', 'Marek Zmyślony', 2023, 'Popularnonaukowe', 'dostepna'],
  ['Listy z Szczytna Małego', 'Zofia Fikcyjna', 2025, 'Powieść', 'dostepna'],
  ['Kuchnia regionu', 'Halina Testowa', 2026, 'Poradnik', 'wypozyczona'],
  ['Drużyna z podwórka', 'Kamil Demo', 2025, 'Dla młodzieży', 'dostepna'],
  ['Zamek na wzgórzu', 'Piotr Fikcyjny', 2026, 'Historia', 'dostepna'],
  ['Dziennik żeglarza', 'Jan Testowy', 2026, 'Reportaż', 'dostepna'],
];
foreach ($ksiazki as $i => [$t, $a, $r, $k, $d]) {
  isv_wezel(['type' => 'ksiazka', 'title' => $t, 'created' => $teraz - (count($ksiazki) - $i) * 3600, 'field_autor' => $a, 'field_rok' => $r, 'field_kategoria' => $k, 'field_dostepnosc' => $d, 'body' => ['value' => '<p>Opis demonstracyjny. Tytuł i autor są fikcyjne.</p>', 'format' => 'full_html']]);
}

// Własne strony błędów po polsku.
$cf->getEditable('system.site')->set('page.404', '/node/' . $id['/nie-znaleziono'])->set('page.403', '/node/' . $id['/brak-dostepu'])->save();

// Menu główne i stopka.
foreach ([['Start', '/start'], ['Aktualności', '/aktualnosci'], ['Wydarzenia', '/wydarzenia'], ['Katalog', '/katalog'], ['Nowości', '/nowosci'], ['Godziny otwarcia', '/node/' . $id['/godziny-otwarcia']], ['Kontakt', '/node/' . $id['/kontakt']]] as $w => [$t, $u]) {
  MenuLinkContent::create(['title' => $t, 'link' => ['uri' => 'internal:' . $u], 'menu_name' => 'main', 'weight' => $w])->save();
}
foreach ([['O bibliotece', '/node/' . $id['/o-bibliotece']], ['Zapisy i regulamin', '/node/' . $id['/zapisy-i-regulamin']], ['Deklaracja dostępności', '/node/' . $id['/deklaracja-dostepnosci']], ['Mapa strony', '/mapa-strony']] as $w => [$t, $u]) {
  MenuLinkContent::create(['title' => $t, 'link' => ['uri' => 'internal:' . $u], 'menu_name' => 'footer', 'weight' => $w])->save();
}
echo "Dane demonstracyjne utworzone.\n";
