# GOOD FINE DINING – Landing Page
[![CI](https://github.com/cascioli/good-fine-dining/actions/workflows/deploy-prod.yml/badge.svg)](https://github.com/cascioli/good-fine-dining/actions/workflows/deploy-prod.yml)
[![CI](https://github.com/cascioli/good-fine-dining/actions/workflows/preview.yml/badge.svg)](https://github.com/cascioli/good-fine-dining/actions/workflows/preview.yml)
[![CI](https://github.com/cascioli/good-fine-dining/actions/workflows/cleanup-preview.yml/badge.svg)](https://github.com/cascioli/good-fine-dining/actions/workflows/cleanup-preview.yml)


Landing page statica ispirata al sito good.simonecascioli.it, sviluppata con HTML5 e CSS3 per massimizzare performance, accessibilità e ottimizzazione SEO.

## Contenuti

Tutti i testi sono forniti dal cliente e riportati integralmente come da specifiche.

## Requisiti

- Qualsiasi browser moderno.
- (Opzionale) Un server statico locale per testare la pagina.

## Avvio del progetto

1. Clona il repository e posizionati nella cartella del progetto.
2. Avvia un server statico, ad esempio con [serve](https://www.npmjs.com/package/serve):
   ```bash
   npx serve .
   ```
   In alternativa, puoi semplicemente aprire `index.html` direttamente nel browser.
3. Visita l'URL indicato nel terminale (ad esempio `http://localhost:3000`) per visualizzare la landing page.

## Struttura del progetto

```
.
├── index.html      # Struttura del markup e metadati SEO
├── styles.css      # Stili globali e responsive design
├── LICENSE         # Licenza del progetto
└── README.md       # Documentazione e istruzioni
```

## Tecnologie utilizzate

- HTML5 semantico
- CSS3 moderno (flexbox, grid, tipografia responsive)
- Google Fonts (Playfair Display, Work Sans)

## Personalizzazione

Gli stili possono essere adattati modificando le variabili CSS definite in `:root`. Le immagini di background possono essere aggiornate intervenendo sulle proprietà `background` delle relative sezioni.

## SEO e Performance

- Markup semantico con intestazioni gerarchiche corrette.
- Meta description ottimizzata.
- Fonts caricati in modo ottimizzato con `preconnect`.
- Layout responsive con attenzione all'accessibilità del contrasto.
