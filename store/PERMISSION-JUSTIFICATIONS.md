# Chrome permissions justification

This file is the source text for the Chrome Web Store Privacy practices form.

## Single purpose

The extension’s single purpose is to let a user initiate a telephone call from
Chrome through a self-hosted FreePBX/Asterisk server.

## `storage`

Stores the user’s PBX URL, API ID, protocol, selected extension and privacy
choices. The API secret is stored in `chrome.storage.session` by default. It is
stored in local extension storage only when the user explicitly selects
“Remember the API secret on this device”. Both storage areas are restricted to
trusted extension contexts.

## `contextMenus`

Adds one selected-text command so a user can right-click a phone number and
initiate a call. The selected value is processed only after the user clicks the
command.

## `notifications`

Shows the accepted/error result for a call initiated from the context menu,
because the popup is not open for that action.

## `scripting`

Registers or unregisters the bundled phone-detection content script only after
the user explicitly enables or disables page phone detection. No code is
downloaded or remotely executed.

## Optional `https://*/*`

Serves two user-initiated cases:

1. the exact HTTPS origin of the customer-configured PBX endpoint; and
2. optional page phone detection when the user separately grants broad page
   access.

The PBX URL validator accepts HTTPS only. Quick call and right-click selection
do not require broad page access.

## Optional `http://*/*`

Used only when the user enables phone detection on non-HTTPS web pages. It is
never accepted as a PBX endpoint.

## Permissions deliberately not requested

- no `tabs`;
- no `webRequest`;
- no cookies;
- no browsing history;
- no clipboard;
- no geolocation;
- no native messaging;
- no static `content_scripts`;
- no non-optional host permissions.
