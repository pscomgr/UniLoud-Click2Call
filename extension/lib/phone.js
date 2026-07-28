(function exposePhone(root) {
  'use strict';

  const PHONE_CANDIDATE = /(?<![\dA-Za-z])(?:\+|00)?\d[\d\s().-]{5,}\d(?![\dA-Za-z])/g;

  function normalizedCandidate(rawValue) {
    const compact = String(rawValue || '').trim().replace(/[\s().-]+/g, '');
    const digits = compact.replace(/\D/g, '');
    if (digits.length < 7 || digits.length > 15) return null;
    if (!/^(?:\+|00)?\d+$/.test(compact)) return null;
    return compact;
  }

  function findCandidates(text) {
    PHONE_CANDIDATE.lastIndex = 0;
    const matches = [];
    for (const match of String(text || '').matchAll(PHONE_CANDIDATE)) {
      const normalized = normalizedCandidate(match[0]);
      if (normalized) {
        matches.push({
          index: match.index || 0,
          text: match[0],
          normalized
        });
      }
    }
    return matches;
  }

  root.C2CPhone = Object.freeze({ normalizedCandidate, findCandidates });
}(globalThis));
