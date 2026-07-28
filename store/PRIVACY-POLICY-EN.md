# Privacy Policy — UniLoud Click-to-Call

Effective date: 28 July 2026

UniLoud Click-to-Call is a Chrome extension that lets users initiate telephone
calls through a FreePBX or Asterisk server selected and operated by their
organization.

## Architecture

The extension does not route calls or data through a UniLoud cloud relay. Call
requests are sent directly over HTTPS to the PBX URL configured by the user or
their administrator. When UniLoud separately operates a managed PBX for a
customer, that service is governed by the applicable customer agreement.

## Data processed

The extension processes:

- PBX HTTPS URL;
- API identifier and API secret;
- SIP/PJSIP choice and selected extension;
- destination phone number when the user requests a call;
- optional sanitized page URL when the user enables that setting.

Automatic phone-number detection is optional. When enabled, page text is
examined locally to make phone-like text clickable. Page text is not uploaded
as a bulk dataset. A chosen number is sent only when the user initiates a call.

Optional page URL context removes URL credentials, query strings and fragments
before transmission.

## Local storage

Connection settings are stored using Chrome extension storage. The API secret
is kept in session storage by default. It is stored persistently only if the
user selects the remember-secret option. Extension storage is restricted to
trusted extension contexts.

Users can clear all local settings from the popup or by uninstalling the
extension.

## PBX processing and logs

The configured PBX verifies credentials and may process the request ID, client
IP, API client, extension, protocol, destination, extension version and result
for security and troubleshooting. Page URLs are not logged by the supplied
backend unless the PBX administrator explicitly enables that option.

The PBX operator determines log retention, access controls and deletion policy.
Contact that organization for requests concerning PBX-side records.

## Sharing and commercial use

The extension does not sell personal data, serve advertising, perform analytics
or use data for creditworthiness or unrelated purposes. It does not transfer
call data to UniLoud or third parties. Data is sent only to the PBX chosen by
the user to perform the requested call.

## Security

The extension requires HTTPS for the PBX endpoint, requests page access only as
an optional user action and does not download or execute remote code. PBX
operators are responsible for TLS certificates, firewall controls, credential
rotation, software updates and extension authorization.

## Children

The product is intended for organizational telephony use and is not directed
to children.

## Changes

Material changes will be reflected in this policy and the effective date.

## Contact

Use the private security or support channel published in the official UniLoud
Click-to-Call GitHub repository. Do not send API secrets or call data in a
public issue.
