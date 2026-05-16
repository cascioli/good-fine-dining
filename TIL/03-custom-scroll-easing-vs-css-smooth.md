# TIL: Smooth scroll custom con `requestAnimationFrame` e offset header sticky

**Progetto:** Good Fine Dining  
**Data:** 2025-10

---

## Perché non `scroll-behavior: smooth`

`scroll-behavior: smooth` non espone parametri per durata o curva di easing — entrambi sono definiti dal browser. Chrome usa circa 300-400ms, Firefox di più. Il risultato varia da browser a browser.

Secondo problema: `scroll-padding-top` funziona solo per la navigazione nativa via hash (il browser che salta a `#cucina` direttamente). Non viene rispettato da `window.scrollTo()`. Con un header sticky, senza gestire l'offset manualmente, il target finisce parzialmente nascosto sotto l'header.

## Implementazione

```javascript
const scrollToTarget = (target) => {
  const offset = header ? header.offsetHeight : 0;
  const destination = target.getBoundingClientRect().top + window.pageYOffset - offset + 8;

  if (prefersReducedMotion.matches) {
    window.scrollTo(0, destination);
    return;
  }

  const start = window.pageYOffset;
  const distance = destination - start;
  const duration = 720;
  let startTime = null;

  const easeInOutCubic = (t) =>
    t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;

  const animateScroll = (currentTime) => {
    if (startTime === null) startTime = currentTime;
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    window.scrollTo(0, start + distance * easeInOutCubic(progress));
    if (elapsed < duration) window.requestAnimationFrame(animateScroll);
  };

  window.requestAnimationFrame(animateScroll);
};
```

Il calcolo della destinazione:
- `getBoundingClientRect().top` → posizione relativa al viewport
- `+ window.pageYOffset` → posizione assoluta nella pagina
- `- header.offsetHeight - 8` → offset per l'header sticky

## `prefers-reduced-motion`

Se l'utente ha attivato la riduzione delle animazioni nel sistema operativo, lo scroll diventa istantaneo. Il check avviene prima di avviare `requestAnimationFrame`.

Lo stesso flag è usato nel CSS per disabilitare le animazioni `[data-animate]`:

```css
@media (prefers-reduced-motion: reduce) {
  [data-animate] {
    opacity: 1;
    transform: none;
    animation: none;
  }
}
```

## Note

`scroll-padding-top` rimane nel CSS perché gestisce il caso in cui l'utente navighi direttamente a un URL con hash (es. `goodfoggia.it/#cucina`). In quel caso il browser gestisce lo scroll in modo nativo e il JS non interviene.
