<?php

namespace Drupal\isvero_demo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Strony listujące serwisu biblioteki (DEMO).
 *
 * Znaczniki tworzone są w szablonach Twig (inline_template) z automatycznym
 * escapowaniem; wszystkie listy mają nagłówki, a tabele – podpis i nagłówki kolumn.
 */
class Biblioteka extends ControllerBase {

  private const MIESIACE = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];

  /** Data po polsku, np. „12 grudnia 2026, 17:00”. */
  public static function data(int $ts, bool $godzina = FALSE): string {
    $tz = new \DateTimeZone('Europe/Warsaw');
    $d = (new \DateTimeImmutable('@' . $ts))->setTimezone($tz);
    $s = (int) $d->format('j') . ' ' . self::MIESIACE[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    return $godzina ? $s . ', ' . $d->format('H:i') : $s;
  }

  /** @return \Drupal\node\NodeInterface[] */
  private function wezly(string $typ, string $sort = 'created', string $kierunek = 'DESC', ?int $limit = NULL, ?callable $warunki = NULL): array {
    $q = $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $typ)
      ->condition('status', 1)
      ->sort($sort, $kierunek);
    if ($warunki) {
      $warunki($q);
    }
    if ($limit) {
      $q->range(0, $limit);
    }
    return $this->entityTypeManager()->getStorage('node')->loadMultiple($q->execute());
  }

  private function wydarzenie(NodeInterface $n): array {
    $ts = strtotime($n->get('field_termin')->value . ' UTC');
    return [
      'tytul' => $n->label(),
      'url' => $n->toUrl()->toString(),
      'data' => self::data($ts, TRUE),
      'iso' => gmdate('c', $ts),
      'miejsce' => $n->get('field_miejsce')->value,
    ];
  }

  private function ksiazka(NodeInterface $n): array {
    return [
      'tytul' => $n->label(),
      'url' => $n->toUrl()->toString(),
      'autor' => $n->get('field_autor')->value,
      'rok' => $n->get('field_rok')->value,
      'kategoria' => $n->get('field_kategoria')->value,
      'dostepna' => $n->get('field_dostepnosc')->value === 'dostepna',
    ];
  }

  private function artykul(NodeInterface $n): array {
    return [
      'tytul' => $n->label(),
      'url' => $n->toUrl()->toString(),
      'data' => self::data((int) $n->getCreatedTime()),
      'iso' => gmdate('c', (int) $n->getCreatedTime()),
      'zajawka' => $n->get('body')->summary ?: mb_strimwidth(trim(strip_tags($n->get('body')->value ?? '')), 0, 180, '…'),
    ];
  }

  private function nadchodzace(?int $limit = NULL): array {
    $teraz = gmdate('Y-m-d\TH:i:s');
    return array_map([$this, 'wydarzenie'], array_values($this->wezly('wydarzenie', 'field_termin', 'ASC', $limit, fn($q) => $q->condition('field_termin', $teraz, '>='))));
  }

  private function render(string $szablon, array $kontekst, array $kontekstyCache = []): array {
    return [
      '#type' => 'inline_template',
      '#template' => $szablon,
      '#context' => $kontekst,
      '#cache' => ['tags' => ['node_list'], 'contexts' => $kontekstyCache, 'max-age' => 3600],
    ];
  }

  private const T_WYDARZENIA = '<ul class="lista-wydarzen">{% for w in wyd %}<li><p class="termin"><time datetime="{{ w.iso }}">{{ w.data }}</time></p><h3><a href="{{ w.url }}">{{ w.tytul }}</a></h3><p class="meta">{{ w.miejsce }}</p></li>{% else %}<li>Brak zaplanowanych wydarzeń.</li>{% endfor %}</ul>';

  private const T_AKTUALNOSCI = '<ul class="lista-wpisow">{% for a in akt %}<li><article><h3><a href="{{ a.url }}">{{ a.tytul }}</a></h3><p class="meta"><time datetime="{{ a.iso }}">{{ a.data }}</time></p><p>{{ a.zajawka }}</p></article></li>{% endfor %}</ul>';

  public function start(): array {
    $kafelki = [
      ['Katalog zbiorów', '/katalog', 'Wyszukaj książkę i sprawdź, czy jest dostępna.'],
      ['Godziny otwarcia', '/godziny-otwarcia', 'Wypożyczalnia, czytelnia i filia w Nowej Wsi.'],
      ['Zapisy i regulamin', '/zapisy-i-regulamin', 'Jak zapisać się do biblioteki i wypożyczać.'],
      ['Wydarzenia', '/wydarzenia', 'Spotkania autorskie, warsztaty, kluby czytelnicze.'],
      ['Nowości', '/nowosci', 'Książki, które niedawno trafiły do zbiorów.'],
      ['Kontakt', '/kontakt', 'Adres, telefon, e-mail i dojazd.'],
    ];
    return $this->render(
      '<p class="wstep">Zapraszamy do wypożyczalni, czytelni i na wydarzenia dla dzieci, młodzieży i dorosłych. Korzystanie z biblioteki jest bezpłatne.</p>
<section aria-labelledby="h-szybko"><h2 id="h-szybko">Na skróty</h2><ul class="kafelki">{% for k in kafelki %}<li><a href="{{ k[1] }}"><span class="kafel-tytul">{{ k[0] }}</span><span class="kafel-opis">{{ k[2] }}</span></a></li>{% endfor %}</ul></section>
<div class="dwie-kolumny">
<section aria-labelledby="h-wyd"><h2 id="h-wyd">Najbliższe wydarzenia</h2>' . self::T_WYDARZENIA . '<p><a class="wiecej" href="/wydarzenia">Wszystkie wydarzenia</a></p></section>
<section aria-labelledby="h-akt"><h2 id="h-akt">Aktualności</h2>' . self::T_AKTUALNOSCI . '<p><a class="wiecej" href="/aktualnosci">Wszystkie aktualności</a></p></section>
</div>
<section aria-labelledby="h-now"><h2 id="h-now">Nowości w zbiorach</h2><ul class="lista-ksiazek">{% for k in nowosci %}<li><a href="{{ k.url }}">{{ k.tytul }}</a> <span class="meta">– {{ k.autor }}, {{ k.rok }}</span></li>{% endfor %}</ul><p><a class="wiecej" href="/nowosci">Wszystkie nowości</a></p></section>',
      [
        'kafelki' => $kafelki,
        'wyd' => $this->nadchodzace(3),
        'akt' => array_map([$this, 'artykul'], array_values($this->wezly('article', 'created', 'DESC', 3))),
        'nowosci' => array_map([$this, 'ksiazka'], array_values($this->wezly('ksiazka', 'created', 'DESC', 4))),
      ],
    );
  }

  public function aktualnosci(): array {
    return $this->render('<p>Komunikaty i informacje biblioteki.</p>' . self::T_AKTUALNOSCI, [
      'akt' => array_map([$this, 'artykul'], array_values($this->wezly('article'))),
    ]);
  }

  public function wydarzenia(): array {
    $teraz = gmdate('Y-m-d\TH:i:s');
    $minione = array_map([$this, 'wydarzenie'], array_values($this->wezly('wydarzenie', 'field_termin', 'DESC', 5, fn($q) => $q->condition('field_termin', $teraz, '<'))));
    return $this->render('<p>Udział we wszystkich wydarzeniach jest bezpłatny. Na warsztaty obowiązują zapisy w wypożyczalni lub telefonicznie.</p><h2>Nadchodzące</h2>' . self::T_WYDARZENIA . '{% if minione %}<h2>Minione</h2><ul class="lista-wydarzen minione">{% for w in minione %}<li><p class="termin"><time datetime="{{ w.iso }}">{{ w.data }}</time></p><h3><a href="{{ w.url }}">{{ w.tytul }}</a></h3></li>{% endfor %}</ul>{% endif %}', [
      'wyd' => $this->nadchodzace(),
      'minione' => $minione,
    ]);
  }

  public function nowosci(): array {
    return $this->render('<p>Książki dodane do zbiorów w ostatnim czasie. Zarezerwujesz je w wypożyczalni lub telefonicznie.</p><ul class="lista-ksiazek">{% for k in lista %}<li><a href="{{ k.url }}">{{ k.tytul }}</a> <span class="meta">– {{ k.autor }}, {{ k.rok }} · {{ k.kategoria }} · {{ k.dostepna ? "dostępna" : "wypożyczona" }}</span></li>{% endfor %}</ul>', [
      'lista' => array_map([$this, 'ksiazka'], array_values($this->wezly('ksiazka', 'created', 'DESC', 12))),
    ]);
  }

  public function katalog(Request $request): array {
    $fraza = mb_substr(trim((string) $request->query->get('q', '')), 0, 100);
    $kategoria = (string) $request->query->get('kategoria', '');
    $kategorie = [];
    foreach ($this->wezly('ksiazka', 'title', 'ASC') as $n) {
      $kategorie[$n->get('field_kategoria')->value] = TRUE;
    }
    $kategorie = array_keys($kategorie);
    sort($kategorie);
    if (!in_array($kategoria, $kategorie, TRUE)) {
      $kategoria = '';
    }
    $szukano = $fraza !== '' || $kategoria !== '';
    $wyniki = [];
    if ($szukano) {
      $wyniki = array_map([$this, 'ksiazka'], array_values($this->wezly('ksiazka', 'title', 'ASC', 50, function ($q) use ($fraza, $kategoria) {
        if ($fraza !== '') {
          $lub = $q->orConditionGroup()->condition('title', $fraza, 'CONTAINS')->condition('field_autor', $fraza, 'CONTAINS');
          $q->condition($lub);
        }
        if ($kategoria !== '') {
          $q->condition('field_kategoria', $kategoria);
        }
      })));
    }
    $liczba = count($wyniki);
    $odm = $liczba === 1 ? 'pozycję' : (($liczba % 10 >= 2 && $liczba % 10 <= 4 && ($liczba % 100 < 10 || $liczba % 100 >= 20)) ? 'pozycje' : 'pozycji');
    return $this->render(
      '<p>Katalog obejmuje przykładowe, fikcyjne pozycje. Wpisz tytuł lub autora albo wybierz dział.</p>
<form class="katalog-form" role="search" method="get" action="/katalog" aria-label="Wyszukiwanie w katalogu">
  <div class="pole"><label for="k-q">Tytuł lub autor</label><input type="search" id="k-q" name="q" value="{{ fraza }}" autocomplete="off"></div>
  <div class="pole"><label for="k-kat">Dział</label><select id="k-kat" name="kategoria"><option value="">Wszystkie działy</option>{% for k in kategorie %}<option value="{{ k }}"{{ k == kategoria ? " selected" : "" }}>{{ k }}</option>{% endfor %}</select></div>
  <button type="submit">Szukaj</button>
</form>
<div role="status" class="wynik-status">{% if szukano %}Znaleziono {{ liczba }} {{ odm }}.{% endif %}</div>
{% if szukano and liczba %}
<p class="meta">Na małym ekranie tabelę można przewijać w poziomie, także klawiszami strzałek po ustawieniu na niej fokusu.</p>
<div class="table-wrap" tabindex="0" role="region" aria-label="Wyniki wyszukiwania w katalogu"><table><caption>Wyniki wyszukiwania{% if fraza %}: „{{ fraza }}”{% endif %}</caption>
<thead><tr><th scope="col">Tytuł</th><th scope="col">Autor</th><th scope="col">Rok</th><th scope="col">Dział</th><th scope="col">Dostępność</th></tr></thead>
<tbody>{% for k in wyniki %}<tr><th scope="row"><a href="{{ k.url }}">{{ k.tytul }}</a></th><td>{{ k.autor }}</td><td>{{ k.rok }}</td><td>{{ k.kategoria }}</td><td>{{ k.dostepna ? "Dostępna" : "Wypożyczona" }}</td></tr>{% endfor %}</tbody></table></div>
{% elseif szukano %}<p>Spróbuj innej frazy lub wybierz „Wszystkie działy”.</p>{% endif %}',
      compact('fraza', 'kategoria', 'kategorie', 'szukano', 'wyniki', 'liczba', 'odm'),
      ['url.query_args:q', 'url.query_args:kategoria'],
    );
  }

  public function mapa(): array {
    $sekcje = [];
    foreach (['main' => 'Menu główne', 'footer' => 'Informacje'] as $menu => $nazwa) {
      $drzewo = \Drupal::menuTree()->load($menu, \Drupal::menuTree()->getCurrentRouteMenuTreeParameters($menu)->setMaxDepth(2));
      $linki = [];
      foreach ($drzewo as $el) {
        if ($el->link->isEnabled()) {
          $linki[] = ['tytul' => $el->link->getTitle(), 'url' => $el->link->getUrlObject()->toString(), 'waga' => $el->link->getWeight()];
        }
      }
      usort($linki, fn($a, $b) => $a['waga'] <=> $b['waga']);
      $sekcje[] = ['nazwa' => $nazwa, 'linki' => $linki];
    }
    return $this->render('{% for s in sekcje %}<h2>{{ s.nazwa }}</h2><ul>{% for l in s.linki %}<li><a href="{{ l.url }}">{{ l.tytul }}</a></li>{% endfor %}</ul>{% endfor %}', ['sekcje' => $sekcje]) + ['#cache' => ['tags' => ['config:system.menu.main', 'config:system.menu.footer']]];
  }

}
