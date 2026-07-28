# Chrome Web Store listing — Ελληνικά

## Όνομα

UniLoud Click-to-Call

## Σύντομη περιγραφή

Κλήσεις από το Chrome μέσω του δικού σας FreePBX ή Asterisk με SIP ή PJSIP.

## Αναλυτική περιγραφή

Το UniLoud Click-to-Call συνδέει το Chrome απευθείας με το self-hosted FreePBX
ή Asterisk της επιχείρησής σας.

Μπορείτε να καλέσετε:

- πληκτρολογώντας αριθμό στο Quick call,
- επιλέγοντας αριθμό και πατώντας δεξί κλικ,
- ενεργοποιώντας προαιρετικά την αναγνώριση τηλεφωνικών αριθμών σε σελίδες.

Ο διαχειριστής εγκαθιστά το PHP 8.x backend στο PBX και δίνει σε κάθε χρήστη
προσωπικό API ID και secret. Το PBX καλεί πρώτα το εσωτερικό του χρήστη και,
όταν απαντήσει, συνδέει τον προορισμό.

Χαρακτηριστικά:

- PJSIP και SIP / chan_sip,
- PJSIP multi-device ringing,
- ρητό allow-list εσωτερικών ανά χρήστη,
- HTTPS-only σύνδεση με το PBX,
- Ελληνικό και Αγγλικό interface,
- session-only API secret από προεπιλογή,
- προαιρετική πρόσβαση στις σελίδες,
- προαιρετικό, καθαρισμένο page URL context.

Δεν υπάρχουν διαφημίσεις, analytics ή UniLoud cloud relay. Η αυτόματη
αναγνώριση τηλεφώνων είναι ανενεργή μέχρι να δοθεί άδεια και τα query strings,
URL credentials και fragments δεν αποστέλλονται ποτέ ως page context.

Απαιτείται συμβατό self-hosted backend. Ζητήστε από τον PBX administrator το
HTTPS URL και τα προσωπικά credentials.
