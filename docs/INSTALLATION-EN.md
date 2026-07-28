# UniLoud Click-to-Call Public v1.4.0 — Installation

This edition is for customers using FreePBX/Asterisk with PJSIP or chan_sip and
no Kaseya BMS integration.

```text
Chrome extension → HTTPS PHP endpoint → localhost AMI → Asterisk
```

There is no UniLoud cloud relay and no database dependency.

## Requirements

- FreePBX or Asterisk with AMI.
- PJSIP or chan_sip.
- PHP 8.x in both the CLI and the web runtime.
- A supported PHP 8.2+ release is recommended for production. PHP 8.0/8.1
  compatibility is retained only for migration.
- Apache/Nginx/PHP-FPM with a valid HTTPS certificate.
- `bash`, `openssl`, `curl`, `python3` and `logrotate`.
- Chrome 102 or newer.

Back up `/etc/asterisk/manager_custom.conf` and
`/etc/asterisk/extensions_custom.conf` before editing them.

## Install the backend

```bash
sha256sum -c SHA256SUMS.txt
unzip UniLoud-Click-to-Call-Public-Backend-v1.4.0.zip
cd UniLoud-Click-to-Call-Public-Backend-v1.4.0

sudo ./install.sh \
  --php-bin /usr/bin/php \
  --web-root /var/www/html/uniloud-click2call
```

Pass `--web-user asterisk` (or the verified Apache/PHP-FPM user) if automatic
detection is not correct. The installer does not change AMI, dialplan, TLS,
firewall or virtual-host configuration.

## Generate and configure credentials

Create a separate API client for each person or managed browser:

```bash
sudo ./tools/generate_credentials.sh browser-user205
sudoedit /etc/uniloud-click2call-public/config.php
```

Store the clear-text `API_SECRET` only in a password manager and the extension.
Put only `API_SECRET_SHA256` in `config.php`. Use the generated `AMI_SECRET` in
both `config.php` and this dedicated `/etc/asterisk/manager_custom.conf` entry:

```ini
[c2c-public]
secret = REPLACE_WITH_AMI_SECRET
deny = 0.0.0.0/0.0.0.0
permit = 127.0.0.1/255.255.255.255
read = none
write = originate
writetimeout = 5000
```

Explicitly configure each client’s allowed protocols and extensions. Adapt the
fail-closed `numbering.allowed_patterns` to the customer’s dial plan.

For PJSIP ring-all, copy the supplied
`asterisk/extensions_custom.conf.example` context to
`/etc/asterisk/extensions_custom.conf`. It uses
`PJSIP_DIAL_CONTACTS()` to dial every registered contact. Set
`ring_all_contacts=false` for chan_sip or direct single-endpoint PJSIP.

```bash
sudo fwconsole reload
cd /usr/local/lib/uniloud-click2call-public
sudo ./tools/preflight.sh
```

## Controlled call

The following test places a real call:

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

The PBX must ring extension 205 first and dial the destination only after the
user answers.

## Chrome setup

For a canary, unzip the Chrome package and load the directory from
`chrome://extensions` using **Load unpacked**.

In the popup:

1. enter the PBX HTTPS base URL and personal API credentials;
2. choose PJSIP or SIP;
3. connect and choose an extension returned by the server;
4. save and make a quick call.

Automatic page phone detection is optional and requests a separate host
permission. Quick call and the selected-text context menu work without it.
Sending a sanitized page URL is a separate opt-in and removes URL credentials,
query strings and fragments.

## Upgrade and rollback

```bash
sudo ./upgrade.sh --php-bin /usr/bin/php \
  --web-root /var/www/html/uniloud-click2call

sudo ./rollback.sh \
  /var/backups/uniloud-click2call-public/upgrade-TIMESTAMP
```

Run preflight and one controlled call after either operation.

See the [Greek guide](INSTALLATION-GR.md) for detailed troubleshooting,
response codes and filesystem paths.
