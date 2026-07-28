# UniLoud Click-to-Call Public

Vendor-neutral Click-to-Call για self-hosted FreePBX και Asterisk.

Τρέχουσα έκδοση: **v1.4.0 Public**

Το προϊόν αποτελείται από:

- Chrome extension Manifest V3, στα Ελληνικά και Αγγλικά,
- self-hosted backend για PHP 8.x,
- σύνδεση με Asterisk μέσω αποκλειστικού localhost AMI λογαριασμού,
- υποστήριξη PJSIP και chan_sip,
- προαιρετικό ring-all για όλα τα PJSIP contacts.

Η public έκδοση δεν περιέχει Kaseya BMS, tickets, CDR, Time Logs, recordings ή
employee mapping. Δεν χρησιμοποιεί UniLoud cloud relay: ο browser επικοινωνεί
μόνο με το HTTPS URL του PBX που ορίζει ο πελάτης.

Κύριοι οδηγοί:

- [Οδηγός εγκατάστασης](docs/INSTALLATION-GR.md)
- [Οδηγός δημοσίευσης στο Chrome Web Store](store/PUBLISHING-GR.md)
- [Πολιτική απορρήτου](store/PRIVACY-POLICY-GR.md)
- [Ασφάλεια](SECURITY.md)

Για production προτείνεται ενεργά υποστηριζόμενη PHP 8.2 ή νεότερη. Η εφαρμογή
διατηρεί τεχνική συμβατότητα με PHP 8.0 και 8.1, αλλά αυτές οι εκδόσεις δεν
πρέπει να χρησιμοποιούνται σε νέα internet-facing εγκατάσταση.

Copyright © 2026 UniLoud Solutions. Με επιφύλαξη παντός δικαιώματος.
