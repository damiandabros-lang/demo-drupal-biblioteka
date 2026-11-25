// Szybki test axe-core (WCAG 2.1 A/AA) dla kluczowych stron, w widoku 1280 px i 320 px.
// Pełny audyt z raportem: repozytorium demo-wcag-audyt.
import { createRequire } from 'node:module';
import { readFile } from 'node:fs/promises';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const axeSrc = await readFile(require.resolve('axe-core/axe.min.js'), 'utf8');
const url = process.argv[2] || 'http://localhost:8080';
const strony = ['/', '/katalog?q=a', '/wydarzenia', '/aktualnosci', '/nowosci', '/mapa-strony', '/deklaracja-dostepnosci', '/godziny-otwarcia', '/nie-ma-takiej-strony'];
const b = await chromium.launch();
let bledy = 0;
for (const widok of [{ width: 1280, height: 900 }, { width: 320, height: 640 }]) {
  const p = await (await b.newContext({ viewport: widok, locale: 'pl-PL' })).newPage();
  for (const s of strony) {
    await p.goto(url + s, { waitUntil: 'networkidle' });
    await p.addScriptTag({ content: axeSrc });
    const r = await p.evaluate(() => axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } }));
    const poziomo = await p.evaluate((w) => document.documentElement.scrollWidth > w + 1, widok.width);
    const n = r.violations.length + (poziomo ? 1 : 0);
    bledy += n;
    console.log(`${n ? 'BŁĄD' : 'OK  '} ${widok.width}px ${s}${r.violations.map((v) => ' ' + v.id).join('')}${poziomo ? ' przewijanie-poziome' : ''}`);
  }
}
await b.close();
process.exit(bledy ? 1 : 0);
