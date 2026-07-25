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
4. Keine Business-/Content-Datenbank (keine Produktdaten, keine Formular-Einträge o. ä.
   dauerhaft in einer DB). **Ausnahme (Phase 2 präzisiert):** SQLite als reiner,
   serverloser Zwischenspeicher für Framework-internen Zustand (Cache, Sessions) ist
   erlaubt — kein DB-Server-Prozess, keine Zugangsdaten, keine Backup-Pflicht, die Datei
   ist ephemer und wird bei Bedarf per `migrate:fresh` neu aufgebaut.
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

### 1.3 Fehlende Produktseiten ergänzen — ✅ abgeschlossen

Statt der ursprünglich angenommenen 6 waren es **7** fehlende Standgeräte-Seiten (zusätzlich
entdeckt: Mindray Nuewa I10, in Nav/Sitemap bereits verlinkt). Direktzugriff auf die Live-Seite
(`dormed.de`) freigeschaltet, dadurch kein Scraper/keine manuelle Content-Zulieferung nötig:
Text, Specs und Bilder wurden 1:1 von der Live-Seite übernommen (Bild-Hashes lagen bereits
lokal in `assets/img/` vor), Yuuble-Metadaten/-Präfixe wie in 1.1/1.2 entfernt, externe
`media.yuuble.de`-Broschürenlinks auf die lokalen PDFs umgebogen. Ausnahme Nuewa I10: der
Live-Seite fehlten Meta-Description/JSON-LD komplett — diese wurden neu verfasst, gestützt
ausschließlich auf tatsächlich auf der Seite genannte Fakten; die dort bewusst fehlende
Spec-Tabelle (Produkt ist brandneu, Live-Seite verweist auf persönliche Beratung statt
Tabelle) wurde unangetastet gelassen.

Nav-Mega-Menü, mobile Nav und `sitemap-system-pages.xml` verlinkten bereits vorher korrekt
auf alle 7 URLs (die Vorgänger-Agentur hatte die Navigation für den vollen Katalog gebaut,
nur die Zielseiten fehlten) — daher war dort keine zusätzliche Änderung nötig.

**DoD:** `ultraschallgeraete/standgeraete/` enthält jetzt alle 12 Geräte (5 bestehende + 7
neue), alle Nav-/Sitemap-Links laufen ins Leere. Jede neue Seite einzeln committed, per
Playwright verifiziert (0 fehlgeschlagene Requests, keine JS-Fehler, vollständiger
Seitenaufbau inkl. Bildergalerie und "Ähnliche Geräte"-Verlinkung).

### 1.4 Globale Stylings zentralisieren

Nur Regeln, die **wirklich auf jeder Seite identisch** vorkommen (Typografie-Basis,
CSS-Custom-Properties/Farben, Reset, generische Button-/Badge-Grundstile aus
`assets/widgets.css`) in eine zentrale globale Stylesheet-Struktur überführen.
Seiten-/Widget-spezifische Inline-`<style>`-Blöcke bleiben in dieser Phase bewusst, wo sie
sind — Komponentisierung/Dedupe ist Aufgabe von Phase 3, nicht von Phase 1.

**DoD:** Keine Duplikate mehr zwischen Dateien für identische globale Werte; alles
Seiten-/Widget-Spezifische ist unangetastet.

### 1.5 Produktbilder in Ordner je Produkt bündeln — ✅ abgeschlossen

Bilder waren flach und unter bedeutungslosen Hash-Dateinamen in `assets/img/` verstreut
(323 Dateien). Für alle 30 Produktseiten (Standgeräte, Mobile Geräte, Handheld) wurde
ermittelt, welche Bilder tatsächlich **einem einzelnen Produkt gehören** (Kriterium: Bild
wird insgesamt auf ≤ 20 Seiten referenziert, und auf genau einer Produktseite eindeutig am
häufigsten — dort läuft Hero-Bild + Thumbnail + JSON-LD zusammen). Diese 224 Bilder wurden
nach `assets/img/produkte/{produkt-slug}/{hash}.{ext}` verschoben, alle Referenzen
site-weit (auch aus fremden Seiten, die ein Produktbild z. B. in einer
"Ähnliche Geräte"-Karte oder einem Fachgebiets-Artikel mitverwenden) auf den neuen Pfad
umgeschrieben. 4 Bilder blieben bewusst im flachen `assets/img/` — sie werden nachweislich
von mehreren, nicht verwandten Produkten geteilt (z. B. ein generisches Beratungsfoto auf
16 verschiedenen Produktseiten) und lassen sich keinem einzelnen Produkt eindeutig
zuordnen. Dateinamen (Hash) bewusst unverändert gelassen, nur die Ordnerstruktur ist neu —
Umbenennung auf sprechende Namen wäre ein separater Schritt, falls gewünscht.

Vorbereitung für später: passt zu `php artisan storage:link`/`public/storage`-Konventionen,
falls Produktbilder in Laravel aus dem Storage statt aus `public/` bedient werden sollen.

**DoD:** `assets/img/produkte/{slug}/` existiert für alle 30 Produkte, 0 kaputte Bildpfade
(automatisiert geprüft), 0 fehlgeschlagene Requests/JS-Fehler auf allen 30 Produktseiten +
7 stichprobenartig geprüften Seiten mit Cross-Links auf Produktbilder, Screenshot-Vergleich
vor/nach pixelgenau identisch.

### Phase-1-Abschlusskriterium

Seite läuft weiterhin **rein statisch** über die bestehende, unveränderte nginx-Config,
verhält sich optisch und funktional exakt wie vorher — jetzt mit vollständigem
Produktkatalog, bereinigtem Markup und aufgeräumter Bilderstruktur je Produkt.

---

## Phase 2 – Laravel-Setup

**Wichtig, gilt ab dieser Phase:** alles, was hier nicht explizit als Schritt ausformuliert
ist, wird vor der Umsetzung mit dem Auftraggeber (Lukas) abgestimmt — keine impliziten
Architektur-/Setup-Entscheidungen mehr treffen. Bereits abgestimmte Zusatzentscheidungen
stehen direkt bei den jeweiligen Schritten.

1. ✅ Aktuellen Stand (= Ergebnis von Phase 1) nach `.old/` verschoben, ab hier unangetastet
   als Referenz für den gesamten weiteren Weg. Laravel-Projekt lebt im Repo-Root (nicht in
   einem Unterordner).
2. ✅ Laravel frisch initialisiert: PHP 8.4, Laravel 13, **kein Starter-Kit** (reines
   Blade, kein React/Vue/Livewire-Scaffolding), **Pest** statt PHPUnit als Test-Framework
   (Composer-Skeleton bringt standardmäßig PHPUnit mit, wurde manuell auf Pest + Pest
   Laravel-Plugin umgestellt).
3. ✅ `laravel/boost` als Dev-Dependency installiert und initialisiert
   (`php artisan boost:install`) — legt u. a. `CLAUDE.md`/`.claude/` mit
   Laravel/Pest/Pint-Konventionen an, ergänzt `AGENTS.md` (Projektkontext), ersetzt es
   nicht.
4. ✅ Aufgeräumt, mit folgenden abgestimmten Zusatzentscheidungen:
   - `users`/`jobs`-Migration und `App\Models\User` inkl. Factory **dauerhaft entfernt**
     (kein Auth-System geplant). `DatabaseSeeder` entsprechend geleert.
   - **Korrektur zu Core Rule 4:** "Keine Datenbank" bezieht sich auf **Business-/
     Content-Daten** (Produkte, Formular-Einträge etc.) — nicht auf SQLite als reinen,
     serverlosen Zwischenspeicher für Framework-internen Zustand. Cache und Sessions
     laufen über SQLite (`DB_CONNECTION=sqlite`, `SESSION_DRIVER=database`,
     `CACHE_STORE=database`, `cache`- und `sessions`-Migration vorhanden). Begründung:
     auf klassischem PHP-FPM-Hosting (Core Rule 5) sammeln sich bei dateibasierten
     Sessions/Cache sehr viele Einzeldateien an; SQLite bündelt das in einer Datei, ohne
     einen eigenen DB-Server-Prozess zu brauchen. Die SQLite-Datei selbst ist **ephemer**
     (gitignored, wird bei jedem Deploy per `migrate:fresh` neu angelegt, keine
     Produktivdaten drin).
   - `QUEUE_CONNECTION=sync` bleibt wie ursprünglich geplant (DB-Queue erst, wenn
     tatsächlich asynchrone Jobs gebraucht werden).
   - `welcome.blade.php` + Default-Route gelöscht, Default-Feature-Test (testete die
     gelöschte Welcome-Seite) mit entfernt, Default-Unit-Test auf Pest-Syntax umgestellt.
   - Laravels eigenes `README.md` behalten (überschreibt unser bisheriges minimales).
   - Default-CI-Workflow (`.github/workflows/`) entfernt.
   - Laravel Pint (Code-Formatter) mit Standard-Konfiguration behalten.
   - **Vite:** Standard-Pipeline wird verwendet (`npm run build` beim Deploy, kein
     Runtime-Prozess). Tailwind CSS und die default Bunny-Fonts-Integration (beide seit
     Laravel 13 auch im "kein Starter-Kit"-Skeleton dabei) **entfernt** — nicht gebraucht,
     wir migrieren bestehendes CSS 1:1, keine Utility-Klassen geplant, keine neuen
     Web-Fonts. Default-Entry-Points `resources/css/app.css`/`resources/js/app.js`
     entfernt; stattdessen `style.css` und `widgets.css` unverändert nach
     `resources/css/` verschoben und als Vite-Entry-Points registriert (`vite.config.js`).
     Einbindung vorerst per `@vite()` einzeln pro Seite (noch keine Layout-Komponente,
     kommt erst Phase 3).
5. ✅ Ordnerstruktur unter `resources/views/` 1:1 zur aktuellen Struktur nachgebaut (77
   Seiten), reines 1:1-Copy-Paste des bereinigten Phase-1-HTML in die Blade-Dateien — noch
   keine Componentisierung, noch keine Blade-Direktiven, noch keine Layouts. Zwei
   mechanische Anpassungen waren dabei nötig (direkte Konsequenz bereits getroffener
   Entscheidungen, keine neuen): die beiden `<link rel="stylesheet">`-Tags wurden durch
   `@vite([...])` ersetzt (Punkt 4), und jedes literale `"@context"` im JSON-LD (67 von 77
   Seiten) musste zu `"@@context"` escaped werden — Laravel 13 hat mittlerweile eine echte
   `@context`/`@endcontext`-Blade-Direktive, die sonst mit dem JSON-LD-Key kollidiert und
   die Seite zum Compile-Fehler bringt. Alle anderen Blade-Direktivnamen wurden vorab
   gegen den gesamten Content-Bestand geprüft, keine weiteren Kollisionen gefunden.
6. ✅ `routes/web.php`: eine `Route::view()` pro Seite (77 Stück, alle benannt), URL-Pfade
   exakt identisch zu jetzt. `sitemap.xml`/`sitemap-system-pages.xml` als eigene Routen
   registriert, lesen aus `resources/sitemap/` (Inhalt 1:1 aus `.old/`), **nicht** aus
   `public/` — siehe Core Rule 2.
7. ✅ `public/assets/img/` und `public/assets/pdf/` unverändert aus `.old/assets/`
   übernommen (identische URL-Pfade), `public/robots.txt` aus `.old/robots.txt` ersetzt
   Laravels Default-`robots.txt`. `assets/widgets.css` geht **nicht** mehr nach `public/`
   (siehe Punkt 4, Vite) — Korrektur gegenüber der ursprünglichen Formulierung dieses
   Schritts.
8. **Infra außerhalb des Repos:** nginx muss von "statisches Fileserving + Rewrite" auf
   einen Laravel-Standard-Serverblock (PHP-FPM, `public/index.php` als Front Controller)
   umgestellt werden. Referenz-Config liegt im Repo-Root als `nginx.conf.example`.
   Hinweis: Die Seite geht zunächst temporär über Coolify live (eigener Reverse Proxy,
   i. d. R. Traefik — diese Datei kommt dort nicht zum Einsatz), erst bei der späteren
   Übertragung auf ein klassisches nginx/PHP-FPM/www-user-Setup auf dem Live-Server wird
   sie tatsächlich verwendet und muss dann mit echten Pfaden/Domain/Socket bestückt werden.

### Phase-2-Abschlusskriterium — ✅ erreicht (Repo-Anteil; Punkt 8 bleibt Infra-Aufgabe)

Identisches Ergebnis zu Phase 1 (URLs, Optik, Verhalten), jetzt aber ausgeliefert durch
Laravel/PHP-FPM statt statischem Fileserver. Kein sichtbarer Unterschied für Besucher oder
Google.

Verifiziert: alle 79 Routen (77 Seiten + 2 Sitemaps) liefern HTTP 200, 0 fehlgeschlagene
Requests/JS-Fehler (Playwright-Sweep). Byte-Vergleich aller 77 Seiten gegen die alte
statische Ausgabe — nach Herausrechnen der beiden erlaubten Unterschiede (Stylesheet-Import
statt Inline-Link, s. o.) — zu 100 % identisch. `sitemap.xml`, `sitemap-system-pages.xml`
und `robots.txt` ebenfalls byte-identisch. Nav-Mega-Menü und Mobile-Burger-Menü funktional
geprüft.

Hinweis für künftige Vorher/Nachher-Vergleiche: `laravel/boost` (Dev-Dependency) injiziert
in `local`/`debug`-Umgebungen ein Browser-Log-Capture-Script in jede Response
(`InjectBoost`-Middleware) — das erzeugt in Screenshot-Diffs sichtbares Pixel-Rauschen
(Text-Antialiasing), obwohl der eigentliche Seiteninhalt unverändert ist. Bei
`composer install --no-dev` bzw. außerhalb von `local`/`debug` taucht das Script gar nicht
erst auf. Für zuverlässige Vergleiche den Byte-Diff-Ansatz (Script rausfiltern) nutzen,
nicht blind auf Pixel-Diff verlassen.

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
2. **Firmen-Empfänger-Adresse wird nicht separat konfiguriert**, sondern aus der
   SMTP-Absenderadresse (`MAIL_FROM_ADDRESS`) abgeleitet — in diesem Setup sind Sender- und
   Empfänger-Postfach der Firmen-Mail identisch (dieselbe Firmen-Mailbox verschickt die Mail
   an sich selbst). Ein eigener `CONTACT_COMPANY_EMAIL`-Env-Var wäre reine Redundanz zu
   `MAIL_FROM_ADDRESS` und entfällt entsprechend. Alle übrigen Angaben (Name, Nachricht,
   Kunden-E-Mail) kommen dynamisch aus dem Formular.
3. **Reply-To der Firmen-Mail** wird auf die vom Kunden im Formular angegebene E-Mail-Adresse
   gesetzt — Mitarbeiter können also direkt aus dem Postfach auf die Anfrage antworten, ohne
   die Adresse manuell rauszusuchen. Konsequenz: die Firmen-Mail landet dadurch faktisch im
   Konversationsverlauf mit dem Kunden (sobald jemand antwortet, sieht der Kunde die
   ursprüngliche Mail ggf. mit in der Historie) — das Template muss entsprechend
   präsentabel/professionell aussehen, nicht wie ein reiner Debug-/Rohdaten-Dump.
4. Zusätzliche zweite Mail als Eingangsbestätigung an den Kunden.
5. **CRM-Anbindung an CAS genesisWorld** (bestehendes CRM-System des Auftraggebers, REST-API
   via OpenAPI/Swagger dokumentiert — ~95 % korrekt, einzelne Endpoints referenzieren
   fehlerhafte Payload-Schemas, z. B. `CreateDataObject`/`GetDataObject` zeigen fälschlich auf
   `CheckForContactDuplicatesRequestData`; Response-Schemas für Create/Query sind teils explizit
   als "currently undocumented" markiert). Jede Formular-Einreichung wird zusätzlich zum
   Mailversand als Datensatz im CRM angelegt.
   - **Auth:** Basic Auth (`<database>/<username>`, gegen die CAS-Benutzer-DB geprüft) **plus**
     `X-CAS-PRODUCT-KEY`-Header, zentral über vier Env-Vars: `CAS_GENESIS_WORLD_HOST`,
     `CAS_GENESIS_WORLD_USERNAME`, `CAS_GENESIS_WORLD_PASSWORD`,
     `CAS_GENESIS_WORLD_PRODUCT_KEY`.
   - **Client:** schlanker eigener Service (`app/Services/Cas/CasClient.php` o. ä.) um Laravels
     `Http`-Facade, kein zusätzliches Composer-Paket. Vermerkt als bewusste Anfangsentscheidung
     für eine einzelne Integration — bei mehreren CRM-Anbindungen später ggf. Wechsel auf
     [Saloon](https://docs.saloon.dev/) (typisierte Connector/Request-Klassen) erwägen, dann
     aber nur mit Rücksprache (neue Dependency).
   - **Ziel-Tabelle `Inquiries`** (existiert im CRM noch nicht, wird vom Auftraggeber dort neu
     angelegt, exakt mit diesen Feldnamen):

     | CAS-Feld | Typ | Formularfeld | Pflicht |
     |---|---|---|---|
     | `NAME` | Text | `name` | ja |
     | `MAIL` | Text | `email` | ja |
     | `PHONE` | Text | `telefon` | nein |
     | `ZIP` | Text | `plz` | ja |
     | `MESSAGE` | Memo/Text | `nachricht` | nein |
     | `COMPANY` | Text | `praxis` | nein |
     | `SPECIALTY` | Text | `fachgebiet` | nein |
     | `CALLBACK_REQUESTED` | Boolean | `rueckruf` | — |
     | `CALLBACK_DATE` | Datum | `rueckruf_datum` | nein |
     | `MAIL_STATUS` | Boolean | wird per PUT nachgetragen (s. u.) | — |

     `datenschutz` (DSGVO-Checkbox) wird **nicht** übernommen — reine
     Absende-Voraussetzung, keine CRM-relevante Information. `CREATED_AT`/vergleichbares wird
     vom CRM selbst automatisch geführt, kein eigenes Datumsfeld dafür nötig.
   - **GUID-Zuordnung für den späteren PUT:** die POST-Response wird auf einen plausiblen
     GUID-Feldnamen geprüft (`GGUID` bevorzugt, passend zur durchgängigen
     `dataObjectGGUID`-Namenskonvention der API; Fallback-Kandidaten `guid`/`id`), roh in
     `api.log` mitgeloggt. Schlägt die Extraktion fehl, wird **kein** PUT versucht und der
     Fehler explizit geloggt statt einen falschen Datensatz zu raten/treffen.
6. **Verarbeitung als Job-Kette** (`Bus::chain()`), nicht als ein einzelner Job — verhindert,
   dass ein Retry nach erfolgreichem CAS-Anlegen einen zweiten (doppelten) CRM-Datensatz
   erzeugt:
   - **Job 1** legt den `Inquiries`-Datensatz per CAS-POST an. Nutzt `retryUntil()` (zeitbasiert,
     nicht feste Versuchsanzahl) — Hintergrund: der CAS-Server rebootet nachts ca. eine Stunde,
     eine Einreichung um Mitternacht muss diese Downtime überstehen und darf nicht als
     endgültig fehlgeschlagen gelten, nur weil eine feste Versuchsanzahl aufgebraucht ist.
   - **Job 2** (erhält die GUID aus Job 1) verschickt Firmen- und Kunden-Mail und macht danach
     den CAS-PUT mit `MAIL_STATUS`. Läuft **erst nach** erfolgreichem Job 1 — vor einem
     erfolgreichen CAS-Anlegen wird **keine** Mail verschickt. Eigenes `retryUntil()`, unabhängig
     von Job 1 (z. B. wenn nur der Mailversand kurz klemmt).
   - **Akzeptiertes Restrisiko:** verarbeitet CAS den POST serverseitig, geht die Antwort aber
     genau im Reboot-Moment verloren, wertet Job 1 das als Fehlschlag und legt beim Retry einen
     zweiten Datensatz an. Ohne Idempotency-Key-Unterstützung auf CAS-Seite (in der Swagger
     nicht vorhanden) nicht zu 100 % vermeidbar — bewusst akzeptiert statt überentwickelt.
   - **Queue-Infrastruktur:** `QUEUE_CONNECTION=database` (ersetzt die bisherige
     `sync`-Annahme aus Phase 2) — die `jobs`/`failed_jobs`-Migration, die in Phase 2 mangels
     Bedarf gelöscht wurde, muss dafür wiederhergestellt werden. **Abarbeitung per Cron**
     (`php artisan queue:work --stop-when-empty`, alle 1–2 Minuten) statt dauerhaft laufendem
     Supervisor-Prozess — passt zu "kein Dauerprozess" (Core Rule 5) und lässt sich später ohne
     Codeänderung auf einen echten Worker umstellen, falls gewünscht (dieselbe Queue, nur eine
     andere Art sie abzuarbeiten).
7. Mail-Views unter `resources/views/mail/contact-form/customer.blade.php` und
   `resources/views/mail/contact-form/company.blade.php` (Laravel-Konvention ist
   `resources/views/mail/...`, nicht `resources/mail/...` — entsprechend korrigiert).
8. **Zwei getrennte Logs**, bewusst nicht in einem File vermischt, da unterschiedliche
   Betrachtungsebenen:
   - **`storage/logs/contact-form.log`** — Formular-/Mail-Funnel, **jede** Einreichung, eine
     Zeile:
     ```
     [TT.MM.JJJJ HH:MM:SS] Anfrage von {NAME} | Mailversand an {Kunden-E-Mail} {✓|✗}
     ```
     Zeitstempel deutsch/menschenlesbar (nicht ISO), Status bezieht sich nur auf den
     Mailversand an den Kunden (Job 2).
   - **`storage/logs/api.log`** — CRM-Anbindung generell (nicht nur Kontaktformular-spezifisch,
     wird bei künftigen weiteren CAS-Anbindungen mitgenutzt): jeder CAS-Request/-Response
     (inkl. roher GUID-Extraktion, s. o.), jeder Fehlschlag/Retry.
9. Feature-Tests für die komplette Logik: Validierungsfehler, Erfolgsfall, Job-Kette wird
   korrekt dispatcht (`Bus::fake()`/`Queue::fake()` + Assertions), beide Mails werden verschickt
   (`Mail::fake()`), CAS-Calls gemockt (`Http::fake()`), inkl. Log-Einträge in beiden Logs für
   Erfolgs- und Fehlerfälle.

### Phase-4-Abschlusskriterium

Kontaktformular versendet echte E-Mails (Firma + Kunde) mit Reply-To auf die Kunden-Adresse
bei der Firmen-Mail, TODO-Marker ist entfernt, jede Einreichung landet zusätzlich als
`Inquiries`-Datensatz im CAS-CRM, jede Einreichung erzeugt eine Zeile in `contact-form.log`
und die zugehörigen CAS-Calls in `api.log`, Cron-basierte Queue-Abarbeitung läuft, Tests grün.

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
5. Die Consent-Entscheidung wird **ausschließlich in einem eigenen, langlebigen
   Browser-Cookie** gespeichert (nicht in der Laravel-DB-Session aus Core Rule 4). Grund:
   Laravel-Sessions sind kurzlebig und leben in der `sessions`-Tabelle der SQLite-DB, die bei
   einem `php artisan migrate:fresh` (z. B. automatisiert bei Redeploys) komplett geleert
   wird — Consent müsste sonst bei jedem Deploy erneut eingeholt werden. Ein eigener Cookie
   ist davon unabhängig und bleibt über Deploys hinweg gültig.

### Phase-5-Abschlusskriterium

Banner sichtbar, dezent, funktional (Zustimmung/Ablehnung wird persistiert, Re-Prompt-Logik
vorbereitet), inhaltlich aber noch "leer", da aktuell laut Übergabe-Dokument keine
Drittanbieter-Dienste aktiv sind.

---

## Phase 6 – Launch (Produktivbetrieb & Analytics)

1. Umzug auf den finalen Server, DNS-Einträge werden auf diesen umgestellt.
2. CI/CD: **zwei GitHub-Actions-Workflows für zwei Branches mit unterschiedlicher
   Deploy-Art**, beide vor der DNS-Umstellung fertig eingerichtet:
   - **`main`** → Produktiv-Deploy auf den finalen Server. Ablauf: Lint + Tests laufen
     zuerst, der Deploy-Job hängt per `needs:` (Job-Dependency) davon ab und läuft nur bei
     grünem Lint/Test-Durchlauf. Der Auftraggeber liefert dafür ein nahezu fertiges
     Workflow-Template mit 1–2 noch offenen Anpassungen.
   - **`claude`** → weiterhin die **Dev-Umgebung** (Coolify), aber der Trigger läuft jetzt
     ebenfalls über GitHub Actions statt über Coolifys eigene Branch-Watch-Funktion: bei
     Push auf `claude` ruft der Workflow lediglich Coolifys Deploy-Webhook per HTTP auf
     (URL/Token als GitHub-Actions-Secret hinterlegt, nicht im Workflow-Code). Kein
     eigenständiges Lint/Test davor nötig — das deckt der `main`-Workflow ab, hier geht es
     nur um den Redeploy-Trigger für die Staging-Umgebung.
3. `APP_ENV`/`APP_DEBUG` auf Produktionswerte umstellen, sobald auf dem finalen Server
   (kein Boost-/Dev-Tooling mehr aktiv) — macht der Auftraggeber selbst zum Umstellungszeitpunkt.
4. Consent-Management aus Phase 5 muss stehen und aktiv sein, bevor Analytics angebunden
   wird — bis zur DNS-Umstellung funktional vorhanden, aber ohne aktive
   Analytics-Anbindung dahinter.
5. Google Analytics und Google Search Console werden **erst unmittelbar nach der
   DNS-Umstellung** angebunden (bestehender Account wird mit der dann unter dormed.de
   laufenden Seite verknüpft — kein Account-Wechsel nötig). Geht technisch erst zu diesem
   Zeitpunkt, da die Seite vorher nicht unter der echten Domain live ist. Eine Datenlücke
   von 2–3 Tagen durch DNS-Propagation ist akzeptiert.

### Phase-6-Abschlusskriterium

Seite läuft produktiv unter dormed.de auf dem finalen Server. CI/CD-Workflow deployt
automatisiert bei Pushes auf `main`, ausschließlich nach erfolgreichem Lint/Test-Lauf.
Coolify/`claude`-Branch bleibt als Dev-Umgebung parallel bestehen. Consent-Banner aktiv,
Analytics/Search Console an den bestehenden Account angebunden.

---

## Phase 7 – Backlog / noch nicht spezifiziert

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
nicht in Phase 1–6 hineingezwungen.
