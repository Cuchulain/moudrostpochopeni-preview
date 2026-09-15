# Podklady pro převod do WordPressu / Elementoru

## Mapa sekcí

| Astro blok | Elementor struktura | Důležitá nastavení |
|---|---|---|
| `SiteHeader` | Header template + Nav Menu | sticky header, mobilní breakpoint 980 px, jedno výrazné CTA |
| `.hero` | hlavní Container se dvěma vnitřními kontejnery | skutečné H1, primární CTA na kontakt, sekundární kotva na průběh |
| `.situations` | Container + 3 opakované karty | na mobilu pod sebou, texty bez diagnóz |
| `.process` | dvousloupcový Container + Icon/List prvky | vlevo sticky pouze na desktopu, na mobilu běžný tok |
| `.services` | Container + 3 karty | nejprve typ podpory, ne seznam diagnóz a léčebných účinků |
| `.about` | obraz + textový Container | kvalifikace doplnit jen po ověření, nepoužívat zavádějící profesní titul |
| `.testimonial` | Testimonial widget / Quote | citaci významově neměnit, zobrazit upozornění na individuální zkušenost |
| `.faq` | Accordion | FAQ obsah musí být totožný s viditelným textem, schema přidat jen pokud je vhodné |
| `.boundary` | úzký informační Container | zobrazit před závěrečným CTA, ne pouze v patičce |
| `.contact` | CTA Container | `tel:` a `mailto:`, později skutečný rezervační odkaz a měření |

## Globální design tokeny

| Token | Hodnota |
|---|---|
| Ink | `#24352f` |
| Paper | `#f6f1e7` |
| Sage | `#758b77` |
| Sage deep | `#415e50` |
| Clay | `#bd7558` |
| Clay pale | `#ead0c1` |
| Display font | Newsreader, 400/500 |
| Body font | DM Sans, 400/500/600 |
| Content width | 1180 px |
| Mobile side padding | 16 px |

## WordPress kontrolní seznam

1. Pracovat na stagingu a vytvořit zálohu.
2. Zachovat jeden H1 a logickou hierarchii H2/H3.
3. V Yoastu upravit title a meta description; nevytvářet druhý canonical ani duplicitní schema graf.
4. Nahrát obrázky do Media Library jako WebP/AVIF, doplnit rozměry a vhodné alt texty.
5. Ověřit, že CTA „Možnosti podpory“ nevede omylem na kontakt.
6. Nastavit události pro hlavní CTA, telefon, e-mail, formulář, začátek a dokončení rezervace.
7. Otestovat šířky 375, 768 a 1280 px, klávesnici, focus, kontrast a reduced motion.
8. Až po schválení odstranit `noindex` ze stagingové kopie a publikovat.

## Co zatím není rozhodnuto

- cílová URL pro případné 301 přesměrování ze staré `/navrat-k-sobe/`; nevytvářet přesměrování bez potvrzení skutečného obsahového nástupce;
- skutečný cíl rezervačního CTA a měření dokončené rezervace;
- potvrzená délka a forma nezávazného rozhovoru;
- definitivní profesní označení a doložitelné vzdělání;
- preferovaná fotografie Dity;
- zda bude na titulní stránce cena, nebo pouze odkaz na ceník;
- finální znění zdravotní hranice po odborné/majitelské kontrole.
