# UniLoud Click-to-Call Public

Vendor-neutral Click-to-Call for self-hosted FreePBX and Asterisk systems.

Current release: **v1.4.0 Public**

The project contains two installable components:

- a Manifest V3 Chrome extension with English and Greek localization;
- a self-hosted PHP 8.x backend that originates calls through a dedicated
  localhost Asterisk Manager Interface (AMI) account.

It supports PJSIP and chan_sip. PJSIP deployments can ring every registered
contact for an extension through `PJSIP_DIAL_CONTACTS()`.

The public edition does **not** contain CDR, Time Log, recording. Call requests go
directly from the extension to the HTTPS PBX URL configured by the customer.

## Documentation

- [Εγκατάσταση στα Ελληνικά](docs/INSTALLATION-GR.md)
- [Installation in English](docs/INSTALLATION-EN.md)
- [Chrome Web Store publishing checklist](store/PUBLISHING-GR.md)
- [Privacy policy](store/PRIVACY-POLICY-EN.md)
- [Security policy](SECURITY.md)

## Runtime support

- PHP 8.0–8.x compatibility; a currently supported PHP 8.2+ release is
  recommended for production.
- FreePBX or Asterisk with AMI originate permission.
- HTTPS endpoint reachable from Chrome.
- PJSIP or chan_sip.
- Chrome 102 or newer.

## Build

```bash
./scripts/scan-secrets.sh
node ./scripts/test-extension.mjs
./scripts/build-release.sh
sha256sum -c dist/SHA256SUMS.txt
```

Release artifacts include separate backend and Chrome Web Store ZIP files.
The Store ZIP has `manifest.json` at its root.

Copyright © 2026 UniLoud Solutions. All rights reserved.
