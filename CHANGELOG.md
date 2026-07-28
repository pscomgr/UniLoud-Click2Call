# Changelog

## 1.4.0 Public — 2026-07-28

- First vendor-neutral public release.
- Added self-hosted PHP 8.x backend for PJSIP and chan_sip.
- Added explicit API-client, extension and protocol allow-lists.
- Added SHA-256 browser-secret verification and dedicated originate-only AMI.
- Added fail-closed destination policy, rate limiting and structured audit log.
- Added PJSIP multi-contact ringing with `PJSIP_DIAL_CONTACTS()`.
- Added Manifest V3 extension with Greek and English localization.
- Added quick call and selected-text context menu without broad page access.
- Added optional, user-initiated page phone detection.
- Added optional sanitized page URL context, disabled by default.
- Added versioned install, upgrade, rollback, uninstall and preflight tooling.
- Added Chrome Web Store listing, disclosure, privacy and reviewer material.

This release contains no BMS, ticket, CDR, recording or cloud-relay integration.
