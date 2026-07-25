# AGENTS.md — dormed.de

Allgemeine Spezifikationen für jeden Agenten (Claude oder anderer), der an diesem Repo
arbeitet. Ergänzt `ROADMAP.md`, ersetzt es nicht — die Roadmap ist der verbindliche
Phasenplan, dieses Dokument ist der Dauerkontext drumherum.

## Projektüberblick

dormed.de ist die Website der SONORING DORMED med. Systeme GmbH (Ultraschallgeräte-
Fachhändler). Das Projekt ist eine Übernahme von einer Vorgänger-Agentur, deren Vertrag
ausgelaufen ist. Der aktuelle Code ist der statische Export eines No-Code-Website-Builders
("Yuuble") und wird schrittweise nach Laravel migriert — siehe `ROADMAP.md` für den
vollständigen Phasenplan.

**Vor jeder Aufgabe: `ROADMAP.md` lesen.** Phasen werden der Reihe nach abgearbeitet, ein
Abschlusskriterium (DoD) muss erfüllt sein, bevor die nächste Phase beginnt.

Seit Phase 2 gibt es zusätzlich zwei weitere Kontext-Quellen, die zusammen mit diesem
Dokument den vollständigen Einstiegspunkt bilden:

- **`CLAUDE.md`** — von `laravel/boost` generierte, Laravel/Pest/Pint-spezifische
  Coding-Guidelines (PHP-Codestil, Testing-Konventionen, wie Boost-MCP-Tools zu nutzen
  sind usw.). Technischer Coding-Standard, kein Projektkontext — ergänzt dieses Dokument,
  überschneidet sich nicht damit.
- **Laravel Boost** (`laravel/boost`, als Dev-Dependency installiert) — stellt einen
  MCP-Server mit Laravel-spezifischen Tools bereit (u. a. `search-docs` für
  versionsgenaue Doku, `database-query`/`database-schema`, `get-absolute-url`,
  `browser-logs`) sowie zwei Skills (`laravel-best-practices`, `pest-testing`) unter
  `.claude/skills/`. Bei Laravel-/Pest-Aufgaben diese Tools/Skills bevorzugen statt
  manueller Shell-Kommandos, siehe `CLAUDE.md` für Details.

## Nicht verhandelbare Leitplanken

(Vollständig in `ROADMAP.md` unter "Core Rules" — hier die Kurzfassung:)

1. Keine URL/Route ändert sich in irgendeiner Phase.
2. `sitemap.xml` wird über eine Route registriert (später), nicht als statische Datei
   ausgeliefert.
3. Styling wird 1:1 optisch übernommen — keine Redesigns, keine Verbesserungen nebenbei.
4. Keine Datenbank.
5. Kein dauerhaft laufender Node/Server-Prozess (klassisches PHP-FPM-Hosting).
6. Das an den Browser ausgelieferte HTML darf sich in keinem Punkt inhaltlich vom
   aktuellen Stand unterscheiden — Ausnahmen nur: Stylesheet-Import statt Inline-Style,
   entfernte Yuuble-Kommentare/-Metadaten, veränderte Klassen-/ID-Namen. JSON-LD,
   SEO-Meta-Tags, Überschriftenstruktur und -Text bleiben identisch.

## Umgebungen & Netzwerkzugriff

- **`dormed.de`** — die aktuell live geschaltete Seite, Hosting läuft noch über die
  Vorgänger-Agentur (Sonderregelung, bis die Übergabe abgeschlossen ist). Nur als
  **Read-only-Referenzquelle** nutzen, z. B. um fehlenden Content 1:1 abzugleichen. Niemals
  als Deploy-Ziel behandeln — wir haben dort keinen Schreibzugriff und sollen auch keinen
  Änderungsanspruch daran haben.
- **`dormed.everding.it`** — Staging-Deployment auf Coolify, gehört dem Auftraggeber
  (Lukas). Er redeployed dort manuell nach relevanten Pushes. **Änderungen im Repo
  erscheinen dort nicht automatisch** — vor einer Ferndiagnose auf dieser Domain im
  Zweifel erst nachfragen, ob schon redeployed wurde. **Geplante Ablösung:** sobald das
  Projekt eine gewisse Struktur erreicht hat, wird diese Domain durch `test.dormed.de`
  ersetzt, dann mit gestreamlinetem Deploy-Schritt (jeder Push löst automatisch ein
  Redeploy aus, kein manuelles Redeployen mehr nötig). Diesen Abschnitt aktualisieren,
  sobald der Wechsel stattgefunden hat.
- Netzwerkzugriff auf neue/andere Domains kann durch die Egress-Policy der Session
  blockiert sein (403 auf CONNECT-Ebene, bevor überhaupt ein Request rausgeht). Das ist
  keine Website-seitige Blockade — nicht umgehen, sondern melden und ggf. um Freigabe
  bitten.

## Lokal testen & verifizieren

- Statischer Server im Repo-Root: `python3 -m http.server <port>`
- Playwright ist in dieser Umgebung global installiert, nicht als Projekt-Dependency.
  Node-Skripte brauchen `NODE_PATH=/opt/node22/lib/node_modules node <script>.js` (Pfad
  kann je nach Umgebung abweichen — `npm root -g` prüfen, falls das nicht passt).
- Test-/Hilfsskripte gehören in ein Scratch-/Temp-Verzeichnis, **nicht ins Repo committen**.
- **Vor jeder Änderung an bestehenden Seiten:** Screenshot-Vergleich vorher/nachher
  (fullPage, Transitions/Animationen per injiziertem CSS abschalten, Seite einmal
  durchscrollen um scroll-reveal-Effekte zu triggern, erst danach screenshotten — sonst
  entstehen Diffs durch Timing statt durch echte Regressionen).
- **Nach jeder Änderung:** auf fehlgeschlagene Netzwerk-Requests (Status ≥ 400) und
  JS-Fehler prüfen (`page.on('response', ...)` / `page.on('pageerror', ...)`).
- Erst committen, wenn Screenshot-Diff und Request-/Fehler-Check beide sauber sind.

## Konventionen im aktuellen (statischen) Code

- Keine `yb-`/`yb_`/`yu-`-Präfixe mehr verwenden (Phase 1.2 hat sie entfernt). Neue
  Klassen/IDs sprechend benennen, ohne Builder-Präfix.
- Keine Yuuble-Builder-Metadaten/-Marker einführen (`data-uuid`, `data-type`,
  `class="yuuble-engine-node"`, `<!-- Yuuble SDK Config -->`, `data-project-id` usw.). Wird
  Content neu von der Live-Seite übernommen (die diese Marker noch aktiv nutzt), zuerst
  durch dieselbe Cleanup-Logik schicken wie in Phase 1.1/1.2 beschrieben.
- Seitenübergreifend identische CSS-Blöcke gehören nach `style.css`, nicht inline pro Seite
  (Phase 1.4).
- Produktbilder, die eindeutig zu einem Produkt gehören, liegen unter
  `assets/img/produkte/{produkt-slug}/{hash}.{ext}`. Echte, mehrfach genutzte Stock-Bilder
  bleiben in `assets/img/` direkt. Datei-Hashes nicht ohne Rücksprache umbenennen.

## Arbeitsweise

- Bei neuen oder fehlenden Seiten: Text/Specs 1:1 von der Live-Seite übernehmen, niemals
  Fakten erfinden. Fehlen Informationen (z. B. keine SEO-Metadaten auf der Live-Seite
  vorhanden), das offen ansprechen statt zu raten — notfalls selbst verfassen, aber klar
  kennzeichnen, dass es keine 1:1-Übernahme ist.
- Scope-Disziplin: nur umsetzen was explizit verlangt wurde. Keine zusätzliche Abstraktion
  oder Refactoring "weil man gerade dabei ist". Bei Unsicherheit über den Umfang
  nachfragen statt anzunehmen.
- Ein Commit pro logischer Einheit (z. B. eine neue Produktseite = ein Commit).
  Commit-Messages erklären das Warum, nicht nur das Was.
- Vor Abschluss einer Phase: die jeweiligen DoD-Kriterien aus `ROADMAP.md` gegenprüfen,
  nicht nur "sieht gut aus" annehmen.

## Bekannte offene Punkte (dokumentiert, aktuell nicht in Bearbeitung)

- Impressum, Datenschutzerklärung und AGB fehlen komplett — Footer-Links auf allen Seiten
  laufen ins Leere. Für eine deutsche Firmenseite rechtlich relevant.
- Rund 30 weitere kaputte interne Links, vorbestehend (Tippfehler in Produkt-/
  Leistungs-Slugs, fehlende Blog-Artikel, zwei fehlende Fachgebiets-Seiten unter `/fuer/`).
  Nicht durch die Migration verursacht, aber noch nicht behoben.
- `assets/img/platzhalter-geraet.svg` in `ultraschallgeraete/gebraucht.html` (Gerät
  "DC-40") ist ein bewusster Platzhalter — die Gebrauchtgeräte-Seite war schon bei der
  Vorgänger-Agentur in Arbeit und wird vom Auftraggeber selbst weitergeführt. Nicht
  anfassen ohne explizite Aufforderung.
