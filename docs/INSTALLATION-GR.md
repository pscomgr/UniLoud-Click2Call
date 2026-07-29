# UniLoud Click-to-Call Public v1.4.0 — Οδηγός εγκατάστασης

Ο οδηγός αφορά πελάτες με FreePBX/Asterisk και εσωτερικά
PJSIP ή chan_sip. Η αρχιτεκτονική είναι:

```text
Chrome extension → HTTPS PHP endpoint → localhost AMI → Asterisk
```

Δεν υπάρχει UniLoud cloud relay και δεν απαιτείται βάση δεδομένων.

## 1. Προϋποθέσεις

- FreePBX ή Asterisk με ενεργό AMI.
- PJSIP ή chan_sip.
- PHP 8.x τόσο στο CLI όσο και στο web runtime.
- Υποστηριζόμενη PHP 8.2+ για production. Οι PHP 8.0/8.1 είναι τεχνικά
  συμβατές αλλά έχουν λήξει και δεν προτείνονται για νέα εγκατάσταση.
- Apache/Nginx/PHP-FPM και έγκυρο HTTPS certificate.
- `bash`, `openssl`, `curl`, `python3`, `logrotate`.
- Chrome 102 ή νεότερο.
- DNS όνομα του PBX που είναι προσβάσιμο από τους browsers.

Πριν ξεκινήσεις, πάρε backup:

```bash
sudo cp -a /etc/asterisk/manager_custom.conf \
  /etc/asterisk/manager_custom.conf.before-c2c
sudo cp -a /etc/asterisk/extensions_custom.conf \
  /etc/asterisk/extensions_custom.conf.before-c2c
```

Μην επεξεργάζεσαι generated αρχεία όπως `manager_additional.conf`.

## 2. Έλεγχος package

Από τον φάκελο όπου βρίσκονται τα artifacts:

```bash
sha256sum -c SHA256SUMS.txt
unzip -t UniLoud-Click-to-Call-Public-Backend-v1.4.0.zip
unzip -t UniLoud-Click-to-Call-Public-Chrome-v1.4.0.zip
```

## 3. Εγκατάσταση backend

```bash
unzip UniLoud-Click-to-Call-Public-Backend-v1.4.0.zip
cd UniLoud-Click-to-Call-Public-Backend-v1.4.0

sudo ./install.sh \
  --php-bin /usr/bin/php \
  --web-root /var/www/html/uniloud-click2call
```

Αν ο installer δεν εντοπίσει σωστά τον web/PHP-FPM χρήστη:

```bash
sudo ./install.sh \
  --php-bin /usr/bin/php \
  --web-user asterisk \
  --web-root /var/www/html/uniloud-click2call
```

Σε Debian/Ubuntu ο web user είναι συχνά `www-data`. Μην τον μαντέψεις:

```bash
ps -eo user=,comm= | grep -E 'httpd|apache2|php-fpm'
```

Η εγκατάσταση δημιουργεί:

```text
/opt/uniloud-click2call-public/releases/1.4.0/
/usr/local/lib/uniloud-click2call-public -> ενεργό release
/etc/uniloud-click2call-public/config.php
/var/lib/uniloud-click2call-public/rate/
/var/log/uniloud-click2call-public/audit.log
```

Ο installer δεν αλλάζει AMI, dialplan, TLS, firewall ή web-server virtual host.

Επιβεβαίωσε ξεχωριστά ότι και το web runtime είναι PHP 8.x. Το ότι το CLI
είναι PHP 8.x δεν αποδεικνύει ότι Apache/PHP-FPM χρησιμοποιεί την ίδια έκδοση.

## 4. Δημιουργία credentials

Δημιούργησε διαφορετικό API client για κάθε χρήστη ή managed browser:

```bash
sudo ./tools/generate_credentials.sh browser-user205
```

Θα εμφανιστούν:

```text
API_ID=...
API_SECRET=...
API_SECRET_SHA256=...
AMI_SECRET=...
```

- Βάλε το `API_SECRET` μόνο στο password manager και στο Chrome extension.
- Βάλε μόνο το `API_SECRET_SHA256` στο `config.php`.
- Βάλε το `AMI_SECRET` στο `config.php` και στο AMI account.
- Μην αποθηκεύσεις clear-text secrets σε Git, ticket, screenshot ή chat.

Για επιπλέον browsers επανέλαβε το command και πρόσθεσε ξεχωριστό
`api_clients` entry. Ένα AMI account μπορεί να χρησιμοποιείται από όλους τους
API clients του ίδιου PBX.

## 5. Ρύθμιση `config.php`

Άνοιξε:

```bash
sudoedit /etc/uniloud-click2call-public/config.php
```

Ελάχιστο PJSIP παράδειγμα:

```php
'api_clients' => array(
    'browser-user205' => array(
        'secret_sha256' => 'ΤΟ_API_SECRET_SHA256',
        'allowed_protocols' => array('pjsip'),
        'extensions' => array(
            '205' => array(
                'name' => 'User 205',
                'protocols' => array('pjsip'),
                'pjsip_endpoint' => '205',
            ),
        ),
    ),
),
```

Για chan_sip:

```php
'allowed_protocols' => array('sip'),
'extensions' => array(
    '205' => array(
        'name' => 'User 205',
        'protocols' => array('sip'),
        'sip_peer' => '205',
    ),
),
```

Κρίσιμα σημεία:

- κάθε API client βλέπει μόνο τα ρητά επιτρεπόμενα εσωτερικά,
- `allowed_patterns` πρέπει να ταιριάζει στο πραγματικό dial plan,
- άδειο `allowed_patterns` απορρίπτει όλες τις κλήσεις,
- το AMI host μένει `127.0.0.1`,
- `require_https` μένει `true`,
- το `config.php` μένει εκτός web root.

Δικαιώματα:

```bash
sudo chown root:"$(stat -c %G /etc/uniloud-click2call-public)" \
  /etc/uniloud-click2call-public/config.php
sudo chmod 0640 /etc/uniloud-click2call-public/config.php
```

## 6. Dedicated AMI account

Πρόσθεσε στο `/etc/asterisk/manager_custom.conf`:

```ini
[c2c-public]
secret = ΤΟ_AMI_SECRET
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
read = none
write = originate
writetimeout = 5000
```

Το username και secret πρέπει να είναι ίδια με:

```php
'ami' => array(
    'host' => '127.0.0.1',
    'port' => 5038,
    'username' => 'c2c-public',
    'secret' => 'ΤΟ_AMI_SECRET',
    'allow_remote' => false,
),
```

Μη χρησιμοποιήσεις FreePBX admin ή γενικό AMI account.

## 7. PJSIP ring-all contacts

Αν ένα PJSIP extension μπορεί να είναι εγγεγραμμένο σε περισσότερες συσκευές,
άφησε:

```php
'ring_all_contacts' => true,
'pjsip_ring_context' => 'custom-c2c-public-ring',
```

και πρόσθεσε το περιεχόμενο του
`asterisk/extensions_custom.conf.example` στο:

```text
/etc/asterisk/extensions_custom.conf
```

Για chan_sip ή PJSIP direct-to-one endpoint μπορείς να ορίσεις:

```php
'ring_all_contacts' => false,
```

Τότε δεν απαιτείται το custom ring context.

Εφάρμοσε τις αλλαγές:

```bash
sudo fwconsole reload
sudo asterisk -rx 'manager show user c2c-public'
sudo asterisk -rx 'core show function PJSIP_DIAL_CONTACTS'
sudo asterisk -rx 'dialplan show custom-c2c-public-ring'
```

Το τελευταίο command χρειάζεται μόνο στο PJSIP ring-all.

## 8. HTTPS endpoint

Το web server πρέπει να εκτελεί τα δύο PHP endpoints:

```text
https://pbx.example.com/uniloud-click2call/originate_call.php
https://pbx.example.com/uniloud-click2call/list_extensions.php
```

Μην εκθέσεις το `/etc/uniloud-click2call-public` ή το release directory ως
static web content. Περιόρισε το firewall στις απαραίτητες πηγές όπου είναι
εφικτό.

Ένα απλό GET πρέπει να επιστρέψει `405 Method Not Allowed`, όχι PHP source:

```bash
curl -i https://pbx.example.com/uniloud-click2call/list_extensions.php
```

Αν το SELinux εμποδίζει το PHP process να συνδεθεί στο localhost AMI, πρόσθεσε
τη στενότερη κατάλληλη τοπική πολιτική. Μην απενεργοποιήσεις το SELinux.

## 9. Preflight

```bash
cd /usr/local/lib/uniloud-click2call-public

sudo ./tools/preflight.sh
```

Όλα τα υποχρεωτικά checks πρέπει να είναι `PASS`.

## 10. Ελεγχόμενη δοκιμαστική κλήση

Το παρακάτω command πραγματοποιεί πραγματική κλήση. Χρησιμοποίησε δοκιμαστικό
προορισμό που επιτρέπεται από το dial plan:

```bash
export C2C_URL='https://pbx.example.com/uniloud-click2call'
export C2C_API_ID='browser-user205'
read -rsp 'API secret: ' C2C_API_SECRET; export C2C_API_SECRET; echo
export C2C_EXTENSION='205'
export C2C_PROTOCOL='pjsip'
export C2C_DESTINATION='2107563001'

./tools/test_endpoint.sh

unset C2C_API_SECRET
```

Αναμενόμενη ροή:

1. χτυπά το εσωτερικό `205`,
2. ο χρήστης απαντά,
3. το Asterisk καλεί τον προορισμό,
4. το endpoint επιστρέφει `success: true`.

Έλεγξε το audit χωρίς να δημοσιεύσεις call data:

```bash
sudo tail -20 /var/log/uniloud-click2call-public/audit.log
```

## 11. Εγκατάσταση Chrome extension

Για canary:

1. Αποσυμπίεσε το `UniLoud-Click-to-Call-Public-Chrome-v1.4.0.zip`.
2. Άνοιξε `chrome://extensions`.
3. Ενεργοποίησε **Developer mode**.
4. Πάτησε **Load unpacked** και επίλεξε τον φάκελο με το `manifest.json`.
5. Άνοιξε το extension και συμπλήρωσε:
   - PBX HTTPS URL,
   - API ID,
   - API secret,
   - PJSIP ή SIP.
6. Πάτησε **Connect and load extensions**.
7. Επίλεξε μόνο το δικό σου εσωτερικό και πάτησε **Save**.

Αν δεν επιλεγεί «Αποθήκευση API secret», το secret παραμένει μόνο στο
`chrome.storage.session` και καθαρίζεται με restart του browser.

Η **Αναγνώριση τηλεφώνων σε σελίδες** είναι προαιρετική και ζητά ξεχωριστή
άδεια. Χωρίς αυτή λειτουργούν κανονικά:

- το Quick call,
- το δεξί κλικ σε επιλεγμένο αριθμό.

Η αποστολή καθαρισμένου page URL είναι επίσης ξεχωριστό opt-in και αφαιρεί
credentials, query string και fragment.

## 12. Αναβάθμιση και rollback

Αναβάθμιση:

```bash
sudo ./upgrade.sh \
  --php-bin /usr/bin/php \
  --web-root /var/www/html/uniloud-click2call
```

Το upgrade δημιουργεί backup κάτω από:

```text
/var/backups/uniloud-click2call-public/upgrade-TIMESTAMP
```

Rollback:

```bash
sudo ./rollback.sh \
  /var/backups/uniloud-click2call-public/upgrade-TIMESTAMP
```

Μετά από upgrade ή rollback τρέξε ξανά preflight και μία controlled call.

## 13. Troubleshooting

Configuration:

```bash
sudo /usr/bin/php \
  /usr/local/lib/uniloud-click2call-public/tools/validate_config.php
```

AMI:

```bash
sudo asterisk -rx 'manager show user c2c-public'
sudo ss -lntp | grep ':5038'
```

Logs:

```bash
sudo tail -100 /var/log/uniloud-click2call-public/audit.log
sudo tail -100 /var/log/httpd/error_log
# ή /var/log/apache2/error.log / PHP-FPM journal, ανά distribution
```

Συχνές αιτίες:

- `401`: λάθος API ID/secret,
- `403`: μη επιτρεπόμενο εσωτερικό ή protocol,
- `426`: το request δεν έφτασε ως HTTPS,
- `429`: ενεργοποιήθηκε rate limit,
- `500`: ελλιπές config ή permissions,
- `502`: AMI login/originate ή dialplan failure,
- `503`: μη εγγράψιμο rate-limit directory.

Το `requestId` της απάντησης αντιστοιχεί στο audit log και είναι ασφαλέστερο
για troubleshooting από την κοινοποίηση ολόκληρου request.
