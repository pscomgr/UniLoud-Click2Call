# Chrome Web Store data-disclosure worksheet

Complete the dashboard against the current wording shown by Google. This is a
conservative mapping for v1.4.0.

## Data handled

| Data | Why | Destination | Default |
|---|---|---|---|
| API ID and API secret | Authenticate to the configured PBX | Customer-configured PBX only | Required |
| PBX URL, protocol, extension | Configure call routing | Local extension storage; PBX request | Required |
| Destination phone number | Place the user-requested call | Customer-configured PBX | Per call |
| Sanitized page URL | Optional call context | Customer-configured PBX | Off |
| Phone-like page text | Local detection and click UI | Remains on device until the user chooses a number | Off |

Depending on the dashboard’s current categories, conservatively disclose:

- **Authentication information** — API ID and secret.
- **Website content** — a selected/detected phone number can be sent when the
  user initiates a call.
- **Web history / website activity** — only if the user enables page URL
  context; query strings, fragments and URL credentials are removed.

The extension does not access or collect health, financial, payment,
geolocation, browsing-history API, email, files, audio, call recording or
keystroke data.

## Data use

Select only the product’s single purpose: call origination and associated
connection/security functionality.

Affirm:

- data is not sold;
- data is not used for advertising, creditworthiness or unrelated purposes;
- data is not transferred to UniLoud or third parties by the extension;
- requests are sent only to the customer-selected PBX;
- there is no human review of user data by the extension developer;
- use complies with the Chrome Web Store Limited Use requirements.

## Storage and retention

- Extension settings remain until the user clears them or removes the
  extension.
- The API secret is session-only unless the user opts into local persistence.
- PBX audit retention is controlled by the PBX operator.
- Rate-limit state is local to the PBX and expires by rolling time window.

## Transport

The extension accepts only an HTTPS PBX URL. The administrator is responsible
for a valid certificate and secure server configuration.
