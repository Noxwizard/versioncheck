# Enhance Platform Integration  
_Comprehensive documentation for the `software/enhance.php` checker_

---

## 1&nbsp;&nbsp;What We’ve Built
`software/enhance.php` adds **real-time version monitoring** for the [Enhance](https://enhance.com) hosting platform to VersionCheck.  
Key capabilities:

* Scrapes the public **Release Notes** page  
  `https://enhance.com/support/release-notes.html`
* Parses every core release (v9 → v12) including patch versions  
  (e.g. `12.6.0`, `12.5.1`, `12.0.30`)
* Detects which release is flagged **“Latest”** and records an _announcement_
  link for notification e-mails
* Stores/updates one entry per **major.minor branch**
  (`12.6`, `12.5`, `11.0`, …) keeping only the highest patch

The integration means administrators and subscribers will be alerted within
minutes of any new Enhance release.

---

## 2&nbsp;&nbsp;How It Works

| Phase | Details |
|-------|---------|
| **Fetch** | `http::get()` (cURL) downloads the raw HTML. |
| **Parse** | `DOMDocument` iterates `<h1>` elements (each version header). |
| **Filter** | Non-core headers (`Appcd…`, `WHMCS…`, `PHP packages`) are ignored. |
| **Extract** | • Version number (`^\d+\.\d+\.\d+`) <br> • Branch = first two octets <br> • Release date from the first subsequent `<h3>` (ex “27th May 2025”) – converted to `Y-m-d H:i:s`. <br> • “Latest” determined by scanning sibling nodes for the word **Latest** (case-insensitive). |
| **Reduce** | All patch releases within the same branch are sorted by PATCH descending; only newest kept. |
| **Return** | Array structure consumed by `check.php`:  ```php ['12.6' => ['version'=>'12.6.0','release_date'=>'2025-05-27 00:00:00','announcement'=>..., 'estimated'=>false], …]``` |

---

## 3&nbsp;&nbsp;Data Extracted

| Field            | Example                  | Notes                                   |
|------------------|--------------------------|-----------------------------------------|
| `software`       | `enhance` (class name)   | Stored automatically by core logic      |
| `branch`         | `12.6`                   | Major.Minor                             |
| `version`        | `12.6.0`                 | Highest patch for branch                |
| `release_date`   | `2025-05-27 00:00:00`    | Parsed; fallback = now + `estimated=1`  |
| `announcement`   | `https://enhance.com/support/release-notes.html#12.6.0` | Only present for branch marked **Latest** |
| `estimated`      | `0`/`1`                  | `1` if date parsing failed              |

---

## 4&nbsp;&nbsp;Setup Instructions

1. **Pull the code**  
   ```bash
   git fetch origin
   git checkout feature/add-enhance-platform
   ```

2. **Verify file exists**  
   ```
   software/enhance.php
   ```

3. **Enable (optional)**  
   `public static $enabled = true;` by default.  
   Set to `false` to disable without deleting the file.

4. **Database**  
   No schema changes. `check.php` will auto-insert rows into `versions` and
   `notifications` tables.

5. **Cron**  
   Ensure your existing cron that executes `check.php` remains active
   (per installation guide). No additional jobs are required.

---

## 5&nbsp;&nbsp;Testing & Troubleshooting

### Quick CLI Test
```bash
php -r "
include 'software/enhance.php';
\$e = new enhance();
\$versions = \$e->get_versions(\$e->get_data());
print_r(array_slice(\$versions,0,3,true));
"
```

Expected trimmed output:
```
Array
(
    [12.6] => Array
        (
            [version] => 12.6.0
            [release_date] => 2025-05-27 00:00:00
            [announcement] => https://enhance.com/support/release-notes.html#12.6.0
            [estimated] => 
        )
    [12.5] => Array
        (
            [version] => 12.5.1
            [release_date] => 2025-05-21 00:00:00
            [estimated] => 
        )
    [12.4] => …
)
```

### Common Issues

| Symptom | Possible Cause | Resolution |
|---------|----------------|------------|
| **`RuntimeException` from `http::get`** | cURL blocked / TLS failure | Check outbound HTTPS, update CA bundle |
| **No versions detected** | HTML structure changed | Inspect page, update DOM traversal/regex |
| **Dates show “estimated”** | Release lacks `<h3>` date or new format | Raise issue; consider regex fallback |
| **Latest not flagged** | “Latest” label moved | Adjust sibling scan range (`$max_siblings_to_check`) |
| **Database errors on first run** | Missing `config.php` or wrong credentials | Follow install README – import SQL & configure PDO |

---

## 6&nbsp;&nbsp;Future Improvements

* **Caching / Rate-limit** – store fetched HTML to cut traffic & guard against
  temporary site outages.
* **Unit tests** – mock HTML fixtures to detect parser breakage early.
* **Full historical capture** – store *all* patch versions, not just newest.
* **Security hardening** – sanitize/limit DOM parsing to reduce attack surface.
* **Graceful fallback** – if DOM parsing fails, attempt pure regex extraction.
* **Official API** – migrate to JSON endpoint if Enhance publishes one.
* **Notification granularity** – allow subscribers to opt into
  major/minor/patch channels separately.

---

### Maintainer Notes
* Written for **PHP ≥8.0** (uses `JSON_THROW_ON_ERROR` in other checkers).  
* Keep `$uri` constant if domain changes (e.g. `.html` vs no extension).  
* Test after core panel redesigns – header hierarchy may shift.
