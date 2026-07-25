# dormed.de – Roadmap: Statisches Yuuble-Export → Laravel

Dieses Dokument ist der verbindliche Leitfaden für die Modernisierung von dormed.de.
Es wird Phase für Phase abgearbeitet — von mir (Claude) oder einem anderen Agenten. Jede
Phase hat ein explizites Abschlusskriterium ("DoD"), das erfüllt sein muss, bevor die
nächste Phase beginnt. ⚠️-Markierungen sind offene Entscheidungen mit einem Default-Wert;
wenn nichts anderes vom Auftraggeber (Lukas) kommuniziert wurde, gilt der Default.

## Nicht verhandelbare Leitplanken (Core Rules)

1. **Keine URL/Route darf sich in irgendeiner Phase ändern.** Die Seite ist seit Jahren
   online, Google kennt sie exakt so, wie sie aktuell vorliegt. Jede Phase muss nach außen
   exakt dieselben URLs liefern wie vorher.
2. `sitemap.xml` wird **nicht** als statische Datei aus `public/` ausgeliefert, sondern von
   Anfang an über eine explizite Route in `routes/web.php` registriert (Inhalt anfangs
   1:1 die aktuelle Datei) — damit sie später ohne URL-Änderung dynamisch generiert werden
   kann.
3. Styling wird 1:1 optisch übernommen. Keine Redesigns, keine "Verbesserungen" nebenbei.
4. Keine Datenbank. Persistenz, falls nötig, über Cookies/Cache/Dateisystem.
5. Kein dauerhaft laufender Node/Server-Prozess. Klassisches PHP-FPM-Hosting.
6. **Das an den Browser ausgelieferte HTML darf sich in keinem Punkt inhaltlich vom
   aktuellen Stand unterscheiden** — mit genau diesen Ausnahmen: zentraler
   Stylesheet-Import statt Inline-`<style>`, entfernte Yuuble-Kommentare/-Metadaten, und
   veränderte Klassen-/ID-Namen. Alles andere bleibt 1:1 identisch: JSON-LD strukturierte
   Daten, SEO-Meta-Tags (title, description, canonical, OG/Twitter), Überschriftenstruktur
   (h1–h6) inklusive Text, alle sichtbaren Textinhalte. Jede Phase, die das nicht
   garantieren kann, wird nicht ohne Rücksprache umgesetzt.

## Phase 0 – Kontext & Grundsatzentscheidungen (abgeschlossen)

Rechercheergebnisse, die diese Roadmap begründen:

- Der aktuelle Code ist der **Export eines No-Code-Website-Builders ("Yuuble")**,
  erkennbar an `data-project-id`/`data-site-id`/`data-context-id`, `data-engine="@yuuble/engine"`
  und `class="yuuble-engine-node"`. Es gibt **kein aktives SDK**: keine `.js`-Dateien, kein
  `<script src>`, keine fetch/XHR/WebSocket-Calls, keine externe `yuuble.*`-Domain. Der
  Kommentar `<!-- Yuuble SDK Config -->` ist ein totes Überbleibsel ohne Wirkung. Was
  tatsächlich läuft, ist reines Vanilla-JS, pro Widget inline eingebettet.
- **70 HTML-Dateien** vorhanden (Übergabe-Dokument nennt 82 — Diskrepanz spricht für
  Unvollständigkeit).
- **6 Produktseiten fehlen nachweislich**: Standgeräte Mindray Consona N9, DC-30, DC-60,
  Nuewa i9, Resona i8, Resona i9. Content-Entwürfe liegen als `.md`-Dateien im Root vor,
  Prospekt-PDFs liegen in `assets/pdf/` — nur die HTML-Seiten wurden nie gebaut.
- Zwei `// TODO: Kein Form-Backend angebunden`-Marker (Kontaktformular, SonoFinder) — beide
  Formulare `preventDefault()`n aktuell nur und tun sonst nichts. Es existiert **kein**
  funktionierender Erfolgs-/Bestätigungszustand.
- Laut Übergabe-Dokument: aktuell keine Cookies, kein Tracking, kein Consent-Tool aktiv.
  Datenschutzerklärung ist veraltet (beschreibt noch altes Setup) und wird bei Bedarf neu
  gepflegt.
- Platzhalterbild für Mindray DC-40 (Gebrauchtgerät) muss durch echtes Produktfoto ersetzt
  werden (`assets/img/platzhalter-geraet.svg`, referenziert in
  `ultraschallgeraete/gebraucht.html`).

Zielarchitektur-Entscheidung: **Laravel**, kein SvelteKit/Inertia.

- Kein SPA-Unterbau nötig, Seite ist inhaltlich ein Content-/Produktkatalog.
- Backend-Logik wird laut Auftraggeber mittelfristig "heavy" (Webhooks, Scheduler, evtl.
  Queues, externe API-Anbindung) — genau Laravels Stärke (Cache, Queue, Scheduler, HTTP
  Client, Events out of the box), ohne DB zu brauchen.
- Klassisches PHP-FPM-Hosting hat **keinen** von uns zu betreibenden Dauerprozess — deckt
  sich mit der Anforderung "kein laufender Prozess". SvelteKit-SSR bräuchte entweder einen
  gemanagten Node-Prozess oder ein Serverless-Target.
- Blade/Alpine statt Inertia+Svelte: vermeidet einen zusätzlichen Node-SSR-Prozess neben
  Laravel und passt besser zu einer 1:1-Styling-Migration von rohem HTML/CSS.

---

## Phase 1 – Vorbereitung (im aktuellen statischen Repo, VOR Laravel)

### 1.1 Yuuble-Kommentare & Engine-Metadaten entfernen

Entfernen: `<!-- Yuuble SDK Config -->` und vergleichbare Kommentare, die Attribute
`data-project-id`, `data-site-id`, `data-context-id`, `data-render-version`,
`data-engine="@yuuble/engine"` auf `<html>`/`<main>`, die Klasse `yuuble-engine-node` sowie
generierte `data-uuid`-Attribute.

⚠️ **Vorsicht:** Viele der `yuuble-engine-node`-Wrapper-Divs tragen inline `style`-Attribute
mit echtem Layout (flex, padding, z-index etc.). Nur die Marker-Attribute/-Klassen/-Kommentare
entfernen, niemals die Wrapper-Divs selbst oder ihre Layout-Styles ungeprüft löschen. Vor
und nach jeder Datei visuell vergleichen (Screenshot-Diff pro Seitentyp reicht, nicht jede
einzelne Seite).

**DoD:** `grep -ri "yuuble"` liefert 0 Treffer mehr in Kommentaren/Meta-Attributen alle
`*.html`-Dateien; Optik unverändert.

### 1.2 Styling-Präfixe bereinigen

Reines Entfernen des Präfixes `yb-`/`yb_`/`yu-` (zwei Buchstaben + Trennzeichen) aus jedem
Klassen- und ID-Namen (in HTML, `style.css`, `assets/widgets.css` und den
Inline-`<style>`-Blöcken pro Widget) — **keine Ersetzung durch neue/sprechende Namen**,
der Rest des Namens bleibt exakt erhalten (`yb-section-abc123` → `section-abc123`,
`yb_button_link` → `button_link`, `yu-header` → `header`). Vor der Ausführung geprüft:
über alle 12.598 betroffenen Vorkommen gibt es keine Kollision — weder zwei
unterschiedliche Original-Namen, die auf denselben gekürzten Namen fallen, noch ein
gekürzter Name, der mit einem bereits vorhandenen, unabhängigen Klassen-/ID-Namen
zusammenstößt.

**DoD:** `grep -rE "yb-|yb_|yu-|yuuble"` liefert 0 Treffer mehr in allen
`*.html`/`*.css`-Dateien.

### 1.3 Fehlende Produktseiten ergänzen

Die 6 fehlenden Standgeräte-Seiten (siehe Phase 0) nachbauen, nach dem Muster der 5
vorhandenen Standgeräte-Seiten (`ultraschallgeraete/standgeraete/*.html`). Ablauf:
Auftraggeber liefert pro Gerät Links oder bereits gescrapten Quellcode, Agent baut die Seite.
Zusätzlich: Platzhalterbild DC-40 ersetzen, sobald ein echtes Foto vorliegt.

**DoD:** `ultraschallgeraete/standgeraete/` enthält alle 11 Geräte (5 bestehende + 6 neue),
`standgeraete/index.html` verlinkt korrekt auf alle, `sitemap.xml` und
`sitemap-system-pages.xml` werden um die neuen URLs ergänzt.

### 1.4 Globale Stylings zentralisieren

Nur Regeln, die **wirklich auf jeder Seite identisch** vorkommen (Typografie-Basis,
CSS-Custom-Properties/Farben, Reset, generische Button-/Badge-Grundstile aus
`assets/widgets.css`) in eine zentrale globale Stylesheet-Struktur überführen.
Seiten-/Widget-spezifische Inline-`<style>`-Blöcke bleiben in dieser Phase bewusst, wo sie
sind — Komponentisierung/Dedupe ist Aufgabe von Phase 3, nicht von Phase 1.

**DoD:** Keine Duplikate mehr zwischen Dateien für identische globale Werte; alles
Seiten-/Widget-Spezifische ist unangetastet.

### Phase-1-Abschlusskriterium

Seite läuft weiterhin **rein statisch** über die bestehende, unveränderte nginx-Config,
verhält sich optisch und funktional exakt wie vorher — jetzt mit vollständigem
Produktkatalog und bereinigtem Markup. Alle Seiten sind (bis auf die neu zentralisierten
globalen Styles) weiterhin self-contained wie jetzt.

---

## Phase 2 – Laravel-Setup

1. Aktuellen Stand (= Ergebnis von Phase 1) nach `.old/` verschieben, ab hier unangetastet
   als Referenz für den gesamten weiteren Weg.
2. Laravel frisch initialisieren. ⚠️ Neueste stabile Version, sofern kein Hosting-Limit bei
   der PHP-Version dagegenspricht (bitte bestätigen).
3. `laravel/boost` als Dev-Dependency installieren (`composer require laravel/boost --dev`)
   und initialisieren (`php artisan boost:install`).
4. Aufräumen: SQLite-Datei und `users`/`cache`/`jobs`-Migrationen löschen (nicht gebraucht),
   `.env`: `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`,
   `DB_CONNECTION` entfernen. Default-Scaffolding (`welcome.blade.php`, Default-Routen)
   löschen.
   - Standard-Laravel-Vite-Pipeline wird verwendet (`npm run build` für gebündelte Assets
     beim Deploy, wie in frischen Laravel-Projekten üblich) — kein Runtime-Prozess, nur
     ein Build-Schritt.
5. Ordnerstruktur unter `resources/views/` 1:1 zur aktuellen Struktur nachbauen (z. B.
   `resources/views/ultraschallgeraete/standgeraete/index.blade.php`), pro Ordner eine
   `index.blade.php`. Für diese Phase: reines 1:1-Copy-Paste des bereinigten Phase-1-HTML
   in die Blade-Dateien — noch keine Componentisierung, noch keine Blade-Direktiven, noch
   keine Layouts.
6. `routes/web.php`: pro Seite eine explizite Route (Laravel hat kein natives
   File-Based-Routing wie Next.js — muss enumeriert werden), URL-Pfade exakt identisch zu
   jetzt. Die bisherigen "schönen URLs ohne .html" ergeben sich mit Laravel-Routing
   automatisch — die aktuell nötige nginx-Rewrite-Regel entfällt.
7. `public/`: `robots.txt`, `assets/img/`, `assets/pdf/`, `assets/widgets.css` unverändert
   nach `public/...` (identische URL-Pfade). **`sitemap.xml` und
   `sitemap-system-pages.xml` NICHT nach `public/`**, sondern als Route in `web.php`
   registrieren, die den aktuellen (statischen) Inhalt zurückgibt — siehe Core Rule 2.
8. **Infra außerhalb des Repos:** nginx muss von "statisches Fileserving + Rewrite" auf
   einen Laravel-Standard-Serverblock (PHP-FPM, `public/index.php` als Front Controller)
   umgestellt werden. Referenz-Config liegt im Repo-Root als `nginx.conf.example`.
   Hinweis: Die Seite geht zunächst temporär über Coolify live (eigener Reverse Proxy,
   i. d. R. Traefik — diese Datei kommt dort nicht zum Einsatz), erst bei der späteren
   Übertragung auf ein klassisches nginx/PHP-FPM/www-user-Setup auf dem Live-Server wird
   sie tatsächlich verwendet und muss dann mit echten Pfaden/Domain/Socket bestückt werden.

### Phase-2-Abschlusskriterium

Identisches Ergebnis zu Phase 1 (URLs, Optik, Verhalten), jetzt aber ausgeliefert durch
Laravel/PHP-FPM statt statischem Fileserver. Kein sichtbarer Unterschied für Besucher oder
Google.

---

## Phase 3 – Komponentenbasiertes Layout

1. Header und Footer als Single-Source-of-Truth-Components in
   `resources/views/components/layout/header.blade.php` und `footer.blade.php`.
2. Layout-Wrapper-Component (`resources/views/components/layout.blade.php`, genutzt als
   `<x-layout>`) als Scaffold für jede Seite. Muss pro Seite unterschiedliche Meta-Daten
   aufnehmen können (aktuell hat **jede Seite eigene** `<title>`, Meta-Description,
   Canonical-URL, OG-Tags, JSON-LD) — dafür Props/Slots: `:title`, `:description`,
   `:canonical`, ggf. ein benannter Slot `head` für JSON-LD, Default-Slot für den
   Seiteninhalt. Globale Assets (zentrales Stylesheet aus Phase 1.4, später das
   Cookie-Consent-Script aus Phase 5) werden hier zentral eingebunden statt pro Seite.
3. Alle Seiten aus Phase 2 auf `<x-layout>` umstellen. **Scope bewusst eng:** in dieser
   Phase wird ausschließlich Header/Footer/Layout-Scaffold komponentisiert — keine weiteren
   Fragmente (Nav-Dropdowns, Badges, Buttons o. ä.) extrahieren, auch wenn sie mehrfach
   vorkommen. Das ist explizit nicht Teil von Phase 3 und wird erst später, falls
   gewünscht, als eigener Schritt angegangen.

### Phase-3-Abschlusskriterium

Gleicher visueller/funktionaler Output wie Phase 2, aber Header/Footer/Meta-Handling kommt
aus einer Quelle statt aus ~70 Kopien.

---

## Phase 4 – Kontaktformular

Ausgangslage: Aktuell passiert nach Validierung serverseitig **nichts** — nur der TODO-Stub,
kein funktionierender Erfolgszustand existiert. Der muss in dieser Phase neu definiert
werden.

1. Serverseitiger Versand via klassischem Laravel-SMTP-Mailer (`MAIL_MAILER=smtp`,
   Zugangsdaten folgen). POST-Route für `/kontakt`, Validierung über eine Form-Request-Klasse.
   **Erfolgszustand:** Von der vorherigen Agentur wurde keine fertige Erfolgs-/Danke-Seite
   geliefert oder im Quellcode hinterlassen (Auftraggeber versucht ggf. noch, den
   Original-Quellcode dafür vom vorherigen Dienstleister zu bekommen). Bis dahin: eigene
   Platzhalter-Erfolgsmeldung nach dem Post/Redirect/Get-Muster zurück auf `/kontakt`
   (keine neue Route, Core Rule bleibt unangetastet), im 1:1-Stil der restlichen Seite
   gestaltet und leicht austauschbar, falls Original-Content nachgeliefert wird. Mail-Body:
   ruhiges, modernes Template, das nur die eingegebenen Daten strukturiert darstellt.
2. Zusätzliche zweite Mail als Eingangsbestätigung an den Kunden.
3. Versand zunächst synchron (`QUEUE_CONNECTION=sync`). Erst nach Validierung im
   Produktivbetrieb Umstellung auf Queue diskutieren.
4. Mail-Views unter `resources/views/mail/contact-form/customer.blade.php` und
   `resources/views/mail/contact-form/company.blade.php` (Laravel-Konvention ist
   `resources/views/mail/...`, nicht `resources/mail/...` — entsprechend korrigiert).
5. Feature-Tests für die komplette Formular-Logik: Validierungsfehler, Erfolgsfall,
   beide Mails werden verschickt (`Mail::fake()` + Assertions).

### Phase-4-Abschlusskriterium

Kontaktformular versendet echte E-Mails (Firma + Kunde), TODO-Marker ist entfernt, Tests
grün.

---

## Phase 5 – Consent-Management (Cookies)

1. `vanilla-cookieconsent` (Orestbida, MIT, kein Branding) als Logik-Layer einbinden —
   Alternative: Klaro!, falls später viele granular togglebare Drittanbieter-Dienste
   dazukommen. Es wird **nur die Logik** genutzt (Kategorien, Consent-State, Re-Prompt bei
   Policy-Änderung, `onAccept`/`onChange`-Hooks), kein vorgefertigtes UI/Popup.
2. Eigenes, **dezentes** Banner-Markup — kein vollflächiges Modal, das die Seite verdeckt —
   gestylt mit den zentralen globalen Styles aus Phase 1.4/Phase 3, eingebunden über die
   Layout-Component aus Phase 3.2.
3. Kategorien-Grundgerüst anlegen (necessary/analytics/marketing), aber noch ohne konkrete
   Dienste — die werden erst angebunden, sobald tatsächlich wieder getrackt wird.
4. Optional, SSR-Vorteil gegenüber reinem Client-Ansatz: Helper/Blade-Directive, um
   eingebettete Drittanbieter-Inhalte (z. B. Maps-Embed) serverseitig erst nach
   vorhandenem Consent-Cookie zu rendern, statt sie client-seitig nachträglich zu blocken.

### Phase-5-Abschlusskriterium

Banner sichtbar, dezent, funktional (Zustimmung/Ablehnung wird persistiert, Re-Prompt-Logik
vorbereitet), inhaltlich aber noch "leer", da aktuell laut Übergabe-Dokument keine
Drittanbieter-Dienste aktiv sind.

---

## Phase 6 – Backlog / noch nicht spezifiziert

Aus den bisherigen Gesprächen relevant, aber noch nicht konkret genug für eine eigene
Phase — wird nachgezogen, sobald die offenen Fragen geklärt sind:

- **Externe System-Integration für Veranstaltungen** (HTML-Caching-Layer): Läuft die
  Anbindung als Push (Webhook ans System) oder Pull (wir pollen die API des externen
  Systems)? Betrifft das nur Veranstaltungen, oder mittelfristig auch den Produktkatalog?
- **Langfristige Content-Pflege:** Bleibt das bei euch/Entwicklern über Git, oder braucht
  es ein Interface für nicht-technisches Personal (z. B. Laravel + Filament)? Entscheidet,
  ob und wann ein Admin-Backend gebraucht wird.
- **Allgemeine Automatisierungs-Vorhaben** — noch nicht konkretisiert, laut Auftraggeber
  "kommt vielleicht, vielleicht auch nicht".
- **SonoFinder:** aktuell ebenfalls nur ein toter Formular-Stub (TODO-Marker). Sobald klar
  ist, was das Tool fachlich tun soll, Entscheidung Alpine.js (rein client-seitig) vs.
  Livewire (falls serverseitige Logik/Daten nötig sind).

Diese Punkte werden zu eigenen, konkreten Phasen, sobald sie spezifiziert sind — bewusst
nicht in Phase 1–5 hineingezwungen.
