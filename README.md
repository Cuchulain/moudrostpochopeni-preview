# Astro preview – Moudrost pochopení

Samostatný prototyp titulní stránky připravený jako podklad pro pozdější převod do WordPressu/Elementoru. Preview je záměrně označené `noindex, nofollow` a nic samo nepublikuje.

## Veřejné preview

Nasazení přes GitHub Pages: https://cuchulain.github.io/moudrostpochopeni-preview/

## Lokální spuštění

```bash
npm install
npm run dev
```

Produkční kontrola:

```bash
npm run build
npm run preview
```

## Referenční snímky

- `docs/preview-desktop.png` – plná stránka při šířce 1440 px
- `docs/preview-mobile.png` – plná stránka při šířce 390 px

Ověřeno s Astro 7.3.2: `astro check` bez chyb a varování, statický build úspěšný, npm audit bez známých zranitelností. Prohlížečový smoke test potvrdil jedno H1, `noindex`, funkční mobilní menu a čistou konzoli.

## Obsahové zdroje

- `../AGENTS.md` – strategie značky a bezpečnostní pravidla
- `../HOMEPAGE_BRIEF.md` – schválený pracovní směr titulní stránky
- `../AUDIT_SUMMARY.md` – priority prvního auditu
- `WORDPRESS_HANDOFF.md` – mapa převodu sekcí do Elementoru

## Poznámka k obrázkům

Preview používá lokální kopii fotografie ze současného webu. Před ostrým nasazením má majitelka potvrdit, kterou finální fotografii chce použít, a zda existuje originál ve vyšší kvalitě.
