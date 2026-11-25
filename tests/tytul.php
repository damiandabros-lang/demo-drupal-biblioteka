<?php
// Izolowana regresja hooka: strona główna bez powtórzenia nazwy, podstrony bez zmian.
class Drupal {
  public static bool $front = TRUE;
  public static function service(string $name): object {
    return new class {
      public function isFrontPage(): bool { return Drupal::$front; }
    };
  }
}
require __DIR__ . '/../web/themes/custom/isvero_biblioteka/isvero_biblioteka.theme';
$v = ['head_title' => ['title' => 'Biblioteka', 'name' => 'Biblioteka – DEMO']];
isvero_biblioteka_preprocess_html($v);
if ($v['head_title'] !== ['name' => 'Biblioteka – DEMO']) throw new RuntimeException('Powtórzony tytuł strony głównej');
Drupal::$front = FALSE;
$v = ['head_title' => ['title' => 'Katalog', 'name' => 'Biblioteka – DEMO']];
$original = $v;
isvero_biblioteka_preprocess_html($v);
if ($v !== $original) throw new RuntimeException('Zmieniony tytuł podstrony');
echo "OK: krótki tytuł strony głównej, DEMO zachowane, tytuł podstrony bez zmian\n";
