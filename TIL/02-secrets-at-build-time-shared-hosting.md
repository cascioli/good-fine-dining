# TIL: Generare file di configurazione in CI su shared hosting senza env vars

**Progetto:** Good Fine Dining  
**Data:** 2025-10

---

Su Aruba shared hosting, PHP non ha accesso a variabili d'ambiente iniettate a runtime. `getenv()` restituisce sempre stringa vuota per qualunque variabile che non sia già nell'ambiente del server. Le credenziali SMTP devono quindi stare in un file PHP, ma non ha senso committarlo nel repository.

## Soluzione: generare il file in pipeline

La pipeline genera `mail_config.php` al momento del deploy usando un heredoc con i valori presi da GitHub Secrets:

```yaml
cat > deploy/feedback/private/mail_config.php <<'PHP'
<?php
return [
    'host'   => '${{ secrets.SMTP_HOST }}',
    'port'   => (int) '${{ secrets.SMTP_PORT }}',
    'secure' => '${{ secrets.SMTP_SECURE }}',
    'user'   => '${{ secrets.SMTP_USER }}',
    'pass'   => '${{ secrets.SMTP_PASS }}',
    'from'   => '${{ secrets.SMTP_FROM }}',
    'to'     => '${{ secrets.SMTP_TO }}',
];
PHP
```

Il file non esiste nel repository. Viene creato nell'artefatto di deploy e caricato su Aruba via FTPS.

## Problema permessi post-FTP

FTP Deploy Action carica i file con permessi 644. Per un file con credenziali in chiaro su hosting condiviso, 640 è preferibile (leggibile solo dall'utente del processo PHP, non da altri utenti sullo stesso host).

Su Aruba non si può fare `chmod` via FTP in modo affidabile. La soluzione è eseguire PHP sul server stesso: la pipeline genera un file `set_perms.php` che chiama `chmod()` sui file della cartella `private/` e poi si cancella:

```php
chmod_safe($privateDir, 0750);
chmod_safe($mailCfg, 0640);
@unlink(__FILE__);
```

Il workflow lo chiama via `curl` subito dopo il deploy:

```yaml
- name: Fix permissions on Aruba
  run: curl -fsSL "https://goodfoggia.it/feedback/private/set_perms.php" || true

- name: Cleanup set_perms
  run: curl -fsSL "https://goodfoggia.it/feedback/private/set_perms.php?cleanup=1" || true
```

La prima chiamata fixa i permessi e il file si cancella da solo. La seconda è un failsafe: se la prima non ha rimosso il file, `?cleanup=1` lo rimuove senza eseguire chmod.

## Protezione via `.htaccess`

La cartella `private/` ha un `.htaccess` che nega l'accesso HTTP a tutti i file tranne `set_perms.php`:

```apache
<Files "set_perms.php">
  Require all granted
</Files>
<FilesMatch "^(?!set_perms\.php$).*\.(php|ini|env|key|pem)$">
  Require all denied
</FilesMatch>
Options -Indexes
```

Anche questo file viene generato in pipeline, non è committato.
