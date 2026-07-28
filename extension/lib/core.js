(function exposeCore(root) {
  'use strict';

  const MAX_PAGE_URL_LENGTH = 2048;

  function normalizePbxUrl(rawValue) {
    let url;
    try {
      url = new URL(String(rawValue || '').trim());
    } catch (_error) {
      throw new Error('INVALID_URL');
    }
    if (url.protocol !== 'https:') {
      throw new Error('HTTPS_REQUIRED');
    }
    url.username = '';
    url.password = '';
    url.search = '';
    url.hash = '';
    if (/\.php$/i.test(url.pathname)) {
      url.pathname = url.pathname.replace(/\/[^/]+\.php$/i, '');
    }
    url.pathname = url.pathname.replace(/\/+$/, '');
    return url.toString().replace(/\/$/, '');
  }

  function buildEndpoint(rawPbxUrl, scriptName) {
    if (!/^[A-Za-z0-9_.-]+\.php$/.test(scriptName)) {
      throw new Error('INVALID_ENDPOINT');
    }
    const base = new URL(normalizePbxUrl(rawPbxUrl));
    base.pathname = `${base.pathname.replace(/\/+$/, '')}/${scriptName}`;
    return base.toString();
  }

  function endpointPermission(rawPbxUrl) {
    const url = new URL(normalizePbxUrl(rawPbxUrl));
    return `${url.origin}/*`;
  }

  function sanitizePageUrl(rawValue) {
    if (!rawValue) return null;
    try {
      const url = new URL(String(rawValue));
      if (!['https:', 'http:'].includes(url.protocol)) return null;
      url.username = '';
      url.password = '';
      url.search = '';
      url.hash = '';
      const value = url.toString();
      return value.length <= MAX_PAGE_URL_LENGTH ? value : null;
    } catch (_error) {
      return null;
    }
  }

  function normalizeDestination(rawValue) {
    const raw = String(rawValue || '')
      .replace(/^tel:/i, '')
      .split(/[;,]/, 1)[0]
      .trim();
    if (!raw || raw.length > 96 || /[\u0000-\u001f\u007f]/.test(raw)) {
      return null;
    }
    const compact = raw.replace(/[\s().-]+/g, '');
    if (!/^(?:\+|00)?\d{2,32}$/.test(compact)) {
      return null;
    }
    return compact;
  }

  root.C2CCore = Object.freeze({
    MAX_PAGE_URL_LENGTH,
    normalizePbxUrl,
    buildEndpoint,
    endpointPermission,
    sanitizePageUrl,
    normalizeDestination
  });
}(globalThis));
