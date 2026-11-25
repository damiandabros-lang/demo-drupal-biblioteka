<?php
/**
 * Korekty treści już zainstalowanego demo (drush php:script przy każdym starcie, idempotentne).
 * Świeża instalacja dostaje te same treści z seed.php.
 */
use Drupal\node\Entity\Node;

$zamiany = [
  'na rzecz zakupu nowości' => 'na zakup nowości',
  'Klub Czytelniczy Dyskusyjny' => 'Dyskusyjny Klub Czytelniczy',
  'Listy z Szczytna Małego' => 'Listy ze Szczytna Małego',
];
$zmienione = 0;
foreach (Node::loadMultiple() as $n) {
  $zmiana = FALSE;
  $tytul = strtr($n->getTitle(), $zamiany);
  if ($tytul !== $n->getTitle()) { $n->setTitle($tytul); $zmiana = TRUE; }
  if ($n->hasField('body') && !$n->get('body')->isEmpty()) {
    $b = $n->get('body')->first()->getValue();
    $nb = $b;
    $nb['value'] = strtr($b['value'] ?? '', $zamiany);
    $nb['summary'] = strtr($b['summary'] ?? '', $zamiany);
    if ($nb !== $b) { $n->set('body', $nb); $zmiana = TRUE; }
  }
  if ($zmiana) { $n->save(); $zmienione++; }
}
echo "[demo] aktualizacje treści: zmieniono $zmienione\n";
