# TIL: `direction: rtl` per invertire l'ordine in CSS Grid

**Progetto:** Good Fine Dining  
**Data:** 2025-10

---

Per le sezioni con layout a due colonne alternato (testo-immagine / immagine-testo) volevo mantenere il testo prima nel DOM — per screen reader e SEO — ma spostarlo visivamente a destra.

## Approccio usato

CSS Grid rispetta la direzione di scrittura per l'ordine delle colonne. Con `direction: rtl` sul contenitore, la prima colonna logica è quella di destra. Aggiungendo `direction: ltr` sui figli, il contenuto interno non eredita la direzione RTL.

```css
.split--reverse {
  direction: rtl;
  text-align: left; /* necessario: rtl allineerebbe il testo a destra */
}

.split--reverse > * {
  direction: ltr;
}
```

Il risultato: il primo figlio nel DOM (testo) va a destra, il secondo (immagine) va a sinistra. Nessuna modifica al markup.

## Confronto con `order: -1`

`order: -1` funziona ma crea una discrepanza tra visual order e source order. Le specifiche ARIA segnalano questo come potenziale problema per la navigazione da tastiera: il tab order segue il DOM, non l'ordine visivo. Con il trick RTL l'ordine rimane quello del DOM in entrambi i casi.

## Edge case

Se nel contenuto ci fossero testi bidirezionali (arabo, ebraico), il reset `direction: ltr` sui figli andrebbe applicato in modo più granulare. In questo progetto il testo è solo italiano, quindi non è un problema.

`text-align: left` sul contenitore è obbligatorio: senza, il testo eredita l'allineamento a destra da `direction: rtl`.
