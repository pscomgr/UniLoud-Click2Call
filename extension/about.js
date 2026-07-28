'use strict';

function t(key, substitutions) {
  return chrome.i18n.getMessage(key, substitutions) || key;
}
document.documentElement.lang = chrome.i18n.getUILanguage().startsWith('el') ? 'el' : 'en';
for (const element of document.querySelectorAll('[data-i18n]')) {
  element.textContent = t(element.dataset.i18n);
}
document.getElementById('version').textContent = t(
  'versionLabel',
  chrome.runtime.getManifest().version
);
