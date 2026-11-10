# Resan – mobilsida för Vietnam → Thailand

Webbsida för Mira och resekompisarna: dagens program, väder, bild från orten och egna foton.
Resan 4 nov – 14 dec 2026 (41 dagar, dag 1 = 4 nov). Kan läggas på hemskärmen (manifest + ikoner).
Visas på Pontus Mac via Übersicht-widgeten `~/Documents/widgets/resan.jsx`, som bäddar in https://jarnlo.se/resan/ i en iframe.
Inbäddad (`embedded`) skickar sidan `postMessage({resanOpen: url})` för länkar med target=_blank; widgeten öppnar dem med `open`.

## Filer
- `index.html` – hela sidan (HTML, CSS, JS i en fil).
- `api.php` – bilder per dag: `list` (GET), `check`/`upload`/`delete` (POST). Kräver PHP med GD.
- `config.sample.php` → kopieras till `config.php` på servern med kodens hash (`password_hash`). `config.php` är aldrig i git.
- `meta.php` – skapas av api.php: namn + ägarhash per bild. Börjar med `<?php exit; ?>` så inget läcker via webben.
- `uploads/` – bilderna (`day-NN-<tid><slump>.jpg`), `uploads/borttagna/` = papperskorg. `.htaccess` spärrar skript där.
- `build.sh` – bygger `resan-inleed.zip` (det som laddas upp till Inleed).

## Var den körs
- **Inleed (målet):** `https://jarnlo.se/resan/`. Pontus laddar upp zip-paketet själv via DirectAdmin och skapar `config.php`.
  Kodlänk till resenärerna: `https://jarnlo.se/resan/#kod=<kod>`.
- **GitHub Pages (tillfälligt):** https://jarnlo.github.io/resan/ från publika repot `jarnlo/resan`. Där finns ingen PHP → uppladdning döljs.
  Ska göras privat/tas bort när Inleed är igång (programmet ska inte ligga publikt).
  Uppdatera: commit + `git push`, ute efter ~1 min.
- Inte som claude.ai-artifact: dess CSP blockerar fetch och bilder från andra värdar.

## Funktioner
- Topp: pilar + Idag (36 px) och tidslinjen (egen pekarstyrd, byts aldrig ut under drag). "Dag X / 41" står direkt under tidslinjen.
  Ort (16 px, krymper till en rad) + på resdagar "färdsätt · ca N km" står som liten rad ovanför programrubriken. Resdagar: "· färdsätt · ca N km" i dagraden och ortnamnet öppnar rutten i Google Maps; annars kartan för orten.
- Fotokredit uppe till höger ("Photo credit: X / Unsplash" – krav från Unsplash, får inte tas bort). Egna foton visar uppladdarens namn där.
- Datum, Framåt/Tillbakablick, två små klockor (Vietnam dag 1–20 / Thailand från dag 21, båda UTC+7, och Sverige), väder (klick → yr.no).
- Bild: Unsplash (nyckel delad med widgeten, 50 sökningar/h totalt!), kvällsfilter (`fitsTod`) bara för idag, byts var 10:e min med övertoning.
  Ingen bild hellre än fel orts bild när kvoten är slut. Egna uppladdade foton ersätter Unsplash den dagen.
- Uppladdning: knappen "Ladda upp foto" (nere till vänster) syns bara med godkänd kod. Koden kommer via länken `#kod=`, kontrolleras,
  sparas (`resan-kod`) och tas bort ur adressen. Namnet frågas första gången (`resan-namn`). Bilden skalas i telefonen och igen på servern
  (1600 px, metadata/GPS bort). Varje telefon har en slumpnyckel (`resan-agare`, header `X-Resan-Owner`); bara den kan ta bort sina bilder.
- Hemskärmsapp på iPhone har eget minne (får inte koden från länken): körs sidan fristående utan kod visas "Har du en kod?" där koden kan skrivas in.
- "Mina foton (n)" bredvid uppladdningsknappen: egna bilder per dag med miniatyrer; tryck på bild = gå till dagen, "Ta bort foto" → "Säker?" → borttagen.
  Egna foton visar en platta uppe till höger: "PHOTO CREDIT" + namn (tryck = nästa), papperskorg för ens egna. Flera foton samma dag:
  små pilar på bildens kanter + tryck var som helst på bilden byter bild. Dagar med uppladdade foton visar bara dem (ingen Unsplash).
- Språk: SV | EN nere till höger (kort för att rymmas vid 360 px), sparas i `resan-sprak`. Texter i `STR`, `DAYS_EN`, `MODE_EN`, `WX_EN`.
- iPhone-hemskärmsapp: statusraden är `black` (inte `black-translucent`). Med genomskinlig statusrad blir webbfönstret kortare än skärmen
  och ett tomt band syns nedtill som inte går att rita bort (testat med mätning, fungerade inte). Ändras inställningen måste ikonen läggas till på nytt.
- Jämn luft: `--pad` 18 px runt hela fönstret (`--pad-top/-bottom/-left/-right`, statusrad/hemindikator går före). Tidslinjen är indragen
  6 px (prickens radie) så att pricken linjerar med knapparna och marginalen.
- Google Analytics `G-5PXLSCYBNC` (konstant `GA_ID`), egendomen "Resan" i Pontus GA-konto. Laddas direkt utan samtyckesfråga (Pontus val,
  trots att svensk lag egentligen kräver samtycke för kakorna). Laddas inte i Mac-widgeten (`embedded`), inte på localhost,
  inte efter `#inte-rakna` (sparas i `resan-ingen-statistik`, `#rakna` slår på igen) och inte om uppladdningsnamnet är "Pontus".
- Exakt en skärm hög, ingen scroll. Svep åt sidan byter dag. Snäpper tillbaka till idag efter 2 min.

## Viktigt
- Programmet (`DAYS`, `TRAVEL`, `LOC`, `PHOTO_Q`) finns bara här. Översätt nya texter i `DAYS_EN`.
  (Gamla widgeten med egen kopia ligger som backup i `~/Documents/widgets/gammal/miras-resa.jsx.bak`.)
- Lokalt test: `.claude/launch.json` kör `php -S 127.0.0.1:8765` här. För uppladdning behövs en lokal `config.php` med en testkod
  (gitignorerad, följer inte med i paketet).
- Testa alltid i mobilstorlek (375×812 och 360 px bredd): ingen scroll, inget som slår i varandra.

## Användarens stil
Pontus vill ha direkta svar, små iterativa visuella ändringar, och att varje ändring verifieras innan den rapporteras.
Kommunikation på svenska.
