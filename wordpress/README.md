# Lokální kopie WordPressu

Lokální kopie ostrého webu (OceanWP + Elementor) pro úpravy šablony. Běží v Dockeru na http://localhost:8080.

## Import zálohy

1. V adminu ostrého webu vytvořit zálohu v UpdraftPlus (databáze + všechny soubory).
2. Stáhnout všechny části do `local/backup/`. Zipy rozbalené Safari nevadí, ale databázi (`…-db.gz`) je nutné stáhnout nerozbalenou – Safari ji při rozbalení poškodí.
3. Spustit `wordpress/import-backup.sh` (pro přepsání existující kopie `--force`).
4. Zálohu na serveru v UpdraftPlus smazat.

Přihlášení je stejné jako na ostrém webu (`/wp-admin`), dvoufázové ověření je lokálně vypnuté.

`local/` je v `.gitignore` – obsahuje databázi s osobními údaji a nesmí do gitu.

## Child šablona a rebrand

`oceanwp-child/` je child šablona OceanWP (písma Newsreader a DM Sans hostovaná lokálně, design tokeny, logo). V Dockeru je připojená přímo z repozitáře.

Nové barvy, písma a logo do databáze převede migrace (dá se pouštět opakovaně):

```bash
docker compose run --rm cli wp eval-file /opt/moudrost/migrations/rebrand.php
```

Aktivuje child šablonu, převezme nastavení Customizeru z OceanWP, nahraje logo, přebarví Elementor (globální barvy a písma, všechny stránky a šablony), podnadpisy OceanWP a cookie lištu Complianz.

## Rozcestník

Pět oblastí (Emoce a psychika, Vztahy změny a výzvy, Bachovy květové esence, Tělo a dotek, Teď jsem na řadě JÁ), jejich služby a texty karet jsou v `oceanwp-child/inc/rozcestnik.php`. Šablona z nich vykresluje:

- `[mp_rozcestnik]` – karty všech oblastí (vložené přes widget Shortcode v Elementoru),
- `[mp_metody oblast="emoce"]` – karty služeb jedné oblasti,
- drobečkovou navigaci pod titulkem stránek, které do oblasti patří,
- 301 přesměrování zrušených stránek (masáže → kraniosakrální terapie).

Stránky oblastí, Psychosomatiku, napojení stávajících stránek a hlavní menu vytvoří migrace (po `rebrand.php`):

```bash
docker compose run --rm cli wp eval-file /opt/moudrost/migrations/rozcestnik.php
```

## Nadpisy

Nadpisy používají globální barvu Elementoru „Starorůžová“ (#96576A) – změnou v Nastavení webu → Globální barvy se přebarví všechny. Velikosti a barvy převede jednorázová migrace (po `rozcestnik.php`, podruhé se nespustí):

```bash
docker compose run --rm cli wp eval-file /opt/moudrost/migrations/headings.php
```

## Bezpečnost lokální kopie

`mu-plugins/local-dev.php` v prostředí `local` blokuje všechny odchozí e-maily (formuláře se tváří jako odeslané, zpráva jen skončí v logu) a zakazuje indexaci.

## Běžné příkazy

```bash
docker compose up -d                                   # spustit
docker compose down                                    # zastavit
docker compose run --rm cli wp plugin list             # WP-CLI
docker compose --profile cli down -v                   # smazat vše včetně databáze
```
