# Chrome Web Store reviewer notes

## Purpose

UniLoud Click-to-Call initiates calls through a customer-controlled
FreePBX/Asterisk server. It does not provide a cloud telephony service.

## Test setup

A functional review requires a temporary PBX endpoint and test extension. Put
the following values only in the private reviewer-instructions field of the
Chrome Web Store dashboard:

```text
PBX HTTPS URL: [TEMPORARY_REVIEW_URL]
API ID: [TEMPORARY_REVIEW_API_ID]
API secret: [TEMPORARY_REVIEW_API_SECRET]
Protocol: PJSIP
Extension: [REVIEW_EXTENSION]
Permitted destination: [REVIEW_DESTINATION]
```

Do not commit reviewer credentials to this repository. Restrict them to a
dedicated test extension and destination, rate-limit them, monitor their use and
revoke them immediately after review.

## Review flow

1. Open the extension popup.
2. Expand Connection settings.
3. Enter the temporary HTTPS PBX URL and credentials.
4. Select PJSIP and click **Connect and load extensions**.
5. Select the returned review extension and click **Save**.
6. Enter the permitted test destination in Quick call and click **Call**.
7. The test extension rings first; after answer, the PBX connects the
   destination.
8. Select a phone number on a page and use the UniLoud context-menu command.
9. Optionally enable page phone detection. This is when broad page host
   permission is requested.

## Privacy and code behavior

- Manifest V3 service worker.
- No remote code, `eval`, obfuscation, analytics, ads or external SDK.
- No UniLoud relay: requests go to the exact PBX origin entered by the reviewer.
- No static content script or mandatory broad page permission.
- Page URL context is off by default and strips credentials, query and fragment.
- The public backend contains no Kaseya BMS or ticket integration.

## Package structure

`manifest.json` is at the root of the uploaded ZIP. Source is unminified and is
identical to the public repository release tag.
