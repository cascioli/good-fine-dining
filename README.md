# Good Fine Dining — Landing Page

[![Deploy to Production](https://github.com/cascioli/good-fine-dining/actions/workflows/deploy-prod.yml/badge.svg)](https://github.com/cascioli/good-fine-dining/actions/workflows/deploy-prod.yml)
[![Preview](https://github.com/cascioli/good-fine-dining/actions/workflows/preview.yml/badge.svg)](https://github.com/cascioli/good-fine-dining/actions/workflows/preview.yml)

Sito vetrina per un ristorante a Foggia. HTML + CSS + un form di feedback in PHP. Nessun framework, nessun bundler, deploy via FTP su Aruba shared hosting.

---

## Struttura del progetto

```
.
├── index.html              # Markup e metadati SEO
├── styles.css              # Stili con CSS custom properties
├── assets/images/          # Immagini in formato WebP
└── feedback/
    ├── index.html          # Pagina form feedback
    ├── send_feedback.php   # Endpoint invio email
    ├── libs/               # PHPMailer (senza Composer)
    └── private/            # Configurazione SMTP (generata in CI)
```

---

## Scelte tecniche

### CSS senza preprocessori

Il foglio CSS usa custom property su `:root` come sistema di token (`--color-accent`, `--radius-lg`, `--shadow-soft`). Nessun build step, nessuna dipendenza esterna.

Per la tipografia e gli spazi usa `clamp()` invece di valori duplicati nei breakpoint:

```css
padding: clamp(3.5rem, 8vw, 6rem);
font-size: clamp(2.1rem, 6vw, 3.6rem);
```

I `@media` query sono usati solo per cambiamenti strutturali che `clamp()` non copre: il passaggio da layout a colonna singola a 2 colonne su `.split` a 768px, l'ordine alternato su `.experience__item--reverse`.

### Layout inverso senza toccare il DOM

Le sezioni con immagine a sinistra e testo a destra usano `direction: rtl` sul contenitore e `direction: ltr` su ogni figlio diretto. CSS Grid rispetta la direzione di scrittura per l'ordine delle colonne, quindi i due elementi si invertono senza modificare la source order nell'HTML.

```css
.split--reverse {
  direction: rtl;
  text-align: left;
}
.split--reverse > * {
  direction: ltr;
}
```

### Animazioni

Gli elementi con attributo `[data-animate]` partono con `opacity: 0` e `transform: translateY(36px)` e vengono animati via CSS animation. Il ritardo si imposta con una custom property inline (`--delay: 0.08s`). Per chi ha `prefers-reduced-motion: reduce`, le animazioni vengono azzerate con `animation: none`.

### Smooth scroll custom

La navigazione tra sezioni usa `requestAnimationFrame` con una curva `easeInOutCubic` a 720ms invece di `scroll-behavior: smooth`. Il motivo pratico: `scroll-behavior: smooth` non permette di controllare durata né easing, e non tiene conto dell'offset dell'header sticky. Lo script calcola manualmente l'offset:

```javascript
const offset = header ? header.offsetHeight : 0;
const destination = target.getBoundingClientRect().top + window.pageYOffset - offset + 8;
```

`scroll-padding-top` su `html` gestisce solo la navigazione nativa via hash, non quella via JS.

### Credenziali SMTP in CI

`feedback/private/mail_config.php` non è committato nel repository. La pipeline lo genera al momento del deploy con un heredoc che legge i valori da GitHub Secrets:

```yaml
cat > deploy/feedback/private/mail_config.php <<'PHP'
<?php
return [
    'host' => '${{ secrets.SMTP_HOST }}',
    'pass' => '${{ secrets.SMTP_PASS }}',
    ...
];
PHP
```

Su Aruba shared hosting non esistono variabili d'ambiente iniettabili a runtime, quindi le credenziali devono stare in un file. Generarlo in CI evita di averlo nel repository.

### Permessi post-deploy

FTP carica i file con permessi 644. Per `mail_config.php` si preferisce 640. La pipeline genera un file `set_perms.php` che viene eseguito via `curl` subito dopo il deploy e poi si cancella:

```php
chmod_safe($mailCfg, 0640);
@unlink(__FILE__);
```

Il workflow lo chiama anche con `?cleanup=1` come secondo passaggio per rimuoverlo nel caso non si fosse già cancellato da solo.

### Preview per ogni PR

Ogni pull request triggera un deploy su `/previews/pr-{N}/` e il workflow posta il link come commento sticky sulla PR. Il merge richiede due approvazioni manuali via GitHub Environments: `review` (approvazione interna) e `client-approval`. Alla chiusura della PR, `cleanup-preview.yml` svuota la cartella remota usando `dangerous-clean-slate: true` su una directory vuota locale.

### PHPMailer con fallback

`send_feedback.php` cerca PHPMailer nell'ordine: Composer autoload → include manuale da `libs/` → fallback su `mail()` nativa. La directory `libs/` ha un `.htaccess` che blocca l'esecuzione diretta dei PHP — i file sono accessibili solo via `require_once`.

---

## Stack

| Layer | Scelta | Note |
|---|---|---|
| Markup | HTML5 semantico | Classi BEM-like |
| Stile | CSS3 + custom properties | Nessun preprocessore |
| Script | Vanilla JS inline | Solo smooth scroll |
| Immagini | WebP | `loading="lazy"` nativo |
| Backend | PHP 8 + PHPMailer | Solo endpoint feedback |
| CI/CD | GitHub Actions | 3 workflow: prod / preview / cleanup |
| Hosting | Aruba shared hosting | Deploy via FTPS |
| Font | Playfair Display + Work Sans | Google Fonts con `preconnect` |
