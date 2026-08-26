# Design-Audit 1.27.3 → 1.28.0

Arbeitsstand der Design-Sanierung. Zwei Teile:

## Die Leinwand

`*.dc.html` plus `canvas.json` sind die Quellen des Audits — sieben Blätter:
Übersicht, Befunde, Token-Vorschlag, Tabelle heute/veredelt, Themes, Fahrplan
und das gemessene Ergebnis. `gridkit-design-audit.html` ist das daraus erzeugte
Dokument (~2,5 MB, deshalb nicht im Repo).

## Die Prüfwerkzeuge — `verify/`

Der Umbau war nur deshalb verantwortbar, weil jede Änderung messbar war.

| Datei | Zweck |
|---|---|
| `inventory.mjs` | Zählt jede Deklaration mit Farbliteral, nach CSS-Abschnitt |
| `typo.mjs` | Vergleicht alle `font-size`-Literale gegen die vorgeschlagene Skala |
| `motion.mjs` | Bilanz zu Übergängen, Animationen und Schatten |
| `apply.mjs` | Wendet die geprüften Zuordnungen aus `maps.json` an |
| `check.mjs` | Vergleicht zwei Messreihen: Regression und Theme-Wirkung |
| `probe-src.js` | Die Sonde selbst — liest berechnete Stile aller Elemente |
| `maps.json` | 273 vorgeschlagene Zuordnungen Literal → Rolle, mit Begründung |
| `prufs.json` | Die gegnerische Prüfung dazu: 5 Blocker, 57 Hinweise |

**Wichtig bei der Sonde:** Übergänge vor dem Messen stilllegen
(`transition: none !important`) und einen echten Frame abwarten. Sonst misst man
Zwischenwerte laufender Transitions — ein Fehler, der bei diesem Audit zunächst
zu falschen Zahlen geführt hat.

Erneut messen:

    php -S 127.0.0.1:8899 -t .
    # Sonde aus probe-src.js im Browser ausführen, Ergebnis als gk-now.json ablegen
    node .design/verify/check.mjs
