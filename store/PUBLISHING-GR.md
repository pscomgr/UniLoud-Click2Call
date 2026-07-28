# Δημοσίευση public v1.4.0 στο Chrome Web Store

Ο οδηγός αφορά αποκλειστικά το public extension. Μην ανεβάσεις το internal
package ή οποιοδήποτε BMS source/store material.

Επίσημες πηγές:

- https://developer.chrome.com/docs/webstore/publish/
- https://developer.chrome.com/docs/webstore/program-policies/
- https://developer.chrome.com/docs/webstore/program-policies/limited-use/
- https://developer.chrome.com/docs/extensions/develop/concepts/declare-permissions

## 1. Προετοιμασία λογαριασμού

1. Χρησιμοποίησε τον εταιρικό Chrome Web Store developer account.
2. Ενεργοποίησε two-step verification.
3. Ολοκλήρωσε registration/fee και publisher verification.
4. Επιβεβαίωσε ότι το εμφανιζόμενο publisher name είναι το εγκεκριμένο
   εταιρικό όνομα.

## 2. Τελικό build

```bash
./scripts/build-release.sh
cd dist
sha256sum -c SHA256SUMS.txt
unzip -t UniLoud-Click-to-Call-Public-Chrome-v1.4.0.zip
unzip -p UniLoud-Click-to-Call-Public-Chrome-v1.4.0.zip manifest.json |
  python3 -m json.tool
```

Φόρτωσε πρώτα το ZIP ως unpacked canary και δοκίμασε:

- νέα εγκατάσταση,
- PJSIP,
- SIP αν υπάρχει test peer,
- quick call,
- selected-text context menu,
- enable/disable page detection,
- session-only και remembered secret,
- restart Chrome,
- clear settings.

## 3. Store listing

Χρησιμοποίησε:

- `LISTING-EN.md` ως primary listing,
- `LISTING-GR.md` ως Greek localization,
- `store-assets/store-icon-128.png`,
- `store-assets/screenshot-01-quick-call.png`,
- `store-assets/screenshot-02-settings.png`,
- `store-assets/small-promo-440x280.png`,
- `store-assets/marquee-1400x560.png`.

Έλεγξε τις τρέχουσες διαστάσεις/απαιτήσεις στο dashboard πριν το submit.

## 4. Privacy

1. Δημοσίευσε το `docs/privacy/index.html` σε σταθερό δημόσιο HTTPS URL.
2. Βάλε αυτό το URL στο Privacy policy field.
3. Αντέγραψε τα permission justifications από
   `PERMISSION-JUSTIFICATIONS.md`.
4. Συμπλήρωσε το questionnaire με βάση το `DATA-DISCLOSURE.md`.
5. Δήλωσε single purpose: call origination through the user-selected PBX.
6. Επιβεβαίωσε Limited Use μόνο αφού συγκρίνεις ξανά package, disclosures και
   policy.

## 5. Reviewer access

Δημιούργησε προσωρινό, απομονωμένο PBX test account:

- ένα test extension,
- έναν μόνο επιτρεπόμενο destination,
- αυστηρό rate limit,
- προσωρινό API secret,
- monitoring και ημερομηνία ανάκλησης.

Βάλε τα στοιχεία μόνο στο private reviewer field, χρησιμοποιώντας το
`REVIEWER-NOTES.md`. Μην τα βάλεις στο repository ή στις screenshots.

## 6. Upload

1. Δημιούργησε νέο item.
2. Ανέβασε μόνο:
   `UniLoud-Click-to-Call-Public-Chrome-v1.4.0.zip`.
3. Συμπλήρωσε Store listing, Privacy practices και Distribution.
4. Διάλεξε public distribution μόνο όταν support/privacy URLs είναι live.
5. Κράτησε screenshot των τελικών disclosures και το SHA-256 του ZIP.
6. Υπέβαλε για review.

Το upload δεν πρέπει να περιέχει:

- backend,
- test credentials,
- `.git`,
- source maps ή remote dependencies,
- internal/BMS αρχεία,
- store working documents.

## 7. Μετά το approval

- Κάνε tag το ακριβές source `v1.4.0`.
- Κράτησε το εγκεκριμένο ZIP και SHA-256.
- Ανάκλησε reviewer credentials.
- Επαλήθευσε το Store item σε καθαρό Chrome profile.
- Για κάθε update αύξησε το τετραμερές-compatible manifest version και
  υπέβαλε νέο package· μην αντικαθιστάς παλιό ZIP σιωπηρά.
