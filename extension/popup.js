'use strict';

const elements = Object.fromEntries([
  'version', 'destination', 'callBtn', 'settingsDetails', 'pbxUrl', 'apiId',
  'apiSecret', 'rememberSecret', 'protocol', 'connectBtn', 'myExtension',
  'sendPageContext', 'saveBtn', 'clearBtn', 'pageAccessState',
  'pageAccessBtn', 'status'
].map((id) => [id, document.getElementById(id)]));
const PAGE_ORIGINS = ['https://*/*', 'http://*/*'];
let directory = [];
let connectedFingerprint = '';
let storedExtension = '';
let pageAccessEnabled = false;

function t(key, substitutions) {
  return chrome.i18n.getMessage(key, substitutions) || key;
}

function localize() {
  document.documentElement.lang = chrome.i18n.getUILanguage().startsWith('el') ? 'el' : 'en';
  for (const element of document.querySelectorAll('[data-i18n]')) {
    element.textContent = t(element.dataset.i18n);
  }
  for (const element of document.querySelectorAll('[data-i18n-placeholder]')) {
    element.placeholder = t(element.dataset.i18nPlaceholder);
  }
}

function runtimeMessage(message) {
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(message, (response) => {
      if (chrome.runtime.lastError) {
        reject(new Error(chrome.runtime.lastError.message));
      } else {
        resolve(response);
      }
    });
  });
}

function showStatus(message, kind = 'ok', timeout = 5000) {
  elements.status.textContent = message;
  elements.status.className = kind;
  clearTimeout(showStatus.timer);
  if (timeout) {
    showStatus.timer = setTimeout(() => {
      elements.status.textContent = '';
      elements.status.className = '';
    }, timeout);
  }
}

function friendlyError(error) {
  return {
    HTTPS_REQUIRED: t('errorHttpsRequired'),
    INVALID_URL: t('errorInvalidUrl')
  }[error?.message] || error?.message || t('errorUnexpected');
}

function fingerprint() {
  return [
    elements.pbxUrl.value.trim(),
    elements.apiId.value.trim(),
    elements.apiSecret.value,
    elements.protocol.value
  ].join('\u001f');
}

function invalidateDirectory() {
  connectedFingerprint = '';
  directory = [];
  rebuildDirectory('', []);
}

function rebuildDirectory(selected, items = directory) {
  directory = Array.isArray(items)
    ? items.filter((item) => item && /^\d{2,8}$/.test(String(item.extension || '')))
    : [];
  elements.myExtension.replaceChildren();
  const empty = document.createElement('option');
  empty.value = '';
  empty.textContent = directory.length ? t('chooseExtension') : t('connectFirst');
  elements.myExtension.appendChild(empty);
  for (const item of directory) {
    const option = document.createElement('option');
    option.value = String(item.extension);
    option.textContent = item.name
      ? `${item.extension} — ${item.name}`
      : String(item.extension);
    elements.myExtension.appendChild(option);
  }
  elements.myExtension.disabled = directory.length === 0;
  if (directory.some((item) => String(item.extension) === selected)) {
    elements.myExtension.value = selected;
  } else if (directory.length === 1) {
    elements.myExtension.value = String(directory[0].extension);
  }
}

function setBusy(busy) {
  for (const button of [
    elements.callBtn,
    elements.connectBtn,
    elements.saveBtn,
    elements.clearBtn,
    elements.pageAccessBtn
  ]) {
    button.disabled = busy;
  }
}

async function loadSettings() {
  const response = await runtimeMessage({ type: 'GET_SETTINGS' });
  if (!response?.success) throw new Error(response?.error || t('errorUnexpected'));
  const settings = response.settings || {};
  elements.pbxUrl.value = settings.pbxUrl || '';
  elements.apiId.value = settings.apiId || '';
  elements.apiSecret.value = settings.apiSecret || '';
  elements.rememberSecret.checked = settings.rememberSecret === true;
  elements.protocol.value = settings.protocol || '';
  elements.sendPageContext.checked = settings.sendPageContext === true;
  storedExtension = settings.myExtension || '';
  if (storedExtension) {
    rebuildDirectory(storedExtension, [{
      extension: storedExtension,
      name: t('storedExtension')
    }]);
  } else {
    rebuildDirectory('', []);
  }
}

async function updatePageAccess() {
  const response = await runtimeMessage({ type: 'PAGE_ACCESS_STATUS' });
  pageAccessEnabled = response?.enabled === true;
  elements.pageAccessState.textContent = pageAccessEnabled
    ? t('pageAccessEnabled')
    : t('pageAccessDisabled');
  elements.pageAccessBtn.textContent = pageAccessEnabled
    ? t('disableButton')
    : t('enableButton');
}

for (const element of [
  elements.pbxUrl,
  elements.apiId,
  elements.apiSecret,
  elements.protocol
]) {
  element.addEventListener(element.tagName === 'SELECT' ? 'change' : 'input', invalidateDirectory);
}

elements.connectBtn.addEventListener('click', async () => {
  setBusy(true);
  try {
    const pbxUrl = C2CCore.normalizePbxUrl(elements.pbxUrl.value);
    const apiId = elements.apiId.value.trim();
    const apiSecret = elements.apiSecret.value;
    const protocol = elements.protocol.value;
    if (!apiId || apiSecret.length < 24 || !['pjsip', 'sip'].includes(protocol)) {
      throw new Error(t('errorIncompleteConnection'));
    }
    const permission = C2CCore.endpointPermission(pbxUrl);
    const granted = await chrome.permissions.request({ origins: [permission] });
    if (!granted) throw new Error(t('errorPbxPermissionDenied'));
    showStatus(t('loadingExtensions'), 'info', 0);
    const response = await runtimeMessage({
      type: 'LIST_EXTENSIONS',
      connection: { pbxUrl, apiId, apiSecret, protocol }
    });
    if (!response?.success) throw new Error(response?.error || t('errorInvalidDirectory'));
    elements.pbxUrl.value = pbxUrl;
    connectedFingerprint = fingerprint();
    rebuildDirectory(storedExtension, response.extensions);
    showStatus(t('extensionsLoaded', String(directory.length)));
  } catch (error) {
    invalidateDirectory();
    showStatus(friendlyError(error), 'error', 7000);
  } finally {
    setBusy(false);
  }
});

elements.saveBtn.addEventListener('click', async () => {
  setBusy(true);
  try {
    if (connectedFingerprint !== fingerprint()) {
      throw new Error(t('errorReconnectRequired'));
    }
    const selected = directory.find(
      (item) => String(item.extension) === elements.myExtension.value
    );
    if (!selected) throw new Error(t('errorChooseExtension'));
    const response = await runtimeMessage({
      type: 'SAVE_SETTINGS',
      settings: {
        pbxUrl: elements.pbxUrl.value,
        apiId: elements.apiId.value,
        apiSecret: elements.apiSecret.value,
        rememberSecret: elements.rememberSecret.checked,
        protocol: elements.protocol.value,
        myExtension: String(selected.extension),
        sendPageContext: elements.sendPageContext.checked
      }
    });
    if (!response?.success) throw new Error(response?.error || t('errorUnexpected'));
    storedExtension = String(selected.extension);
    showStatus(t('settingsSaved'));
  } catch (error) {
    showStatus(error.message, 'error', 7000);
  } finally {
    setBusy(false);
  }
});

elements.callBtn.addEventListener('click', async () => {
  const destination = C2CCore.normalizeDestination(elements.destination.value);
  if (!destination) {
    showStatus(t('errorInvalidNumber'), 'error');
    return;
  }
  setBusy(true);
  showStatus(t('queueingCall'), 'info', 0);
  try {
    const response = await runtimeMessage({ type: 'CLICK_TO_CALL', destination });
    if (!response?.success) throw new Error(response?.error || t('errorUnexpected'));
    elements.destination.value = '';
    showStatus(response.message || t('notificationQueued'));
  } catch (error) {
    showStatus(error.message, 'error', 7000);
  } finally {
    setBusy(false);
  }
});

elements.destination.addEventListener('keydown', (event) => {
  if (event.key === 'Enter') elements.callBtn.click();
});

elements.clearBtn.addEventListener('click', async () => {
  if (!confirm(t('confirmClear'))) return;
  setBusy(true);
  try {
    await runtimeMessage({ type: 'CLEAR_SETTINGS' });
    connectedFingerprint = '';
    storedExtension = '';
    for (const id of ['pbxUrl', 'apiId', 'apiSecret', 'destination']) {
      elements[id].value = '';
    }
    elements.protocol.value = '';
    elements.rememberSecret.checked = false;
    elements.sendPageContext.checked = false;
    rebuildDirectory('', []);
    showStatus(t('settingsCleared'));
  } finally {
    setBusy(false);
  }
});

elements.pageAccessBtn.addEventListener('click', async () => {
  setBusy(true);
  try {
    const changed = pageAccessEnabled
      ? await chrome.permissions.remove({ origins: PAGE_ORIGINS })
      : await chrome.permissions.request({ origins: PAGE_ORIGINS });
    if (!changed && !pageAccessEnabled) {
      throw new Error(t('errorPagePermissionDenied'));
    }
    if (pageAccessEnabled && elements.pbxUrl.value.trim()) {
      const pbxPermission = C2CCore.endpointPermission(elements.pbxUrl.value);
      const retained = await chrome.permissions.contains({
        origins: [pbxPermission]
      });
      if (!retained) {
        const restored = await chrome.permissions.request({
          origins: [pbxPermission]
        });
        if (!restored) {
          throw new Error(t('errorPbxPermissionDenied'));
        }
      }
    }
    await runtimeMessage({ type: 'SYNC_PAGE_ACCESS' });
    await updatePageAccess();
  } catch (error) {
    showStatus(friendlyError(error), 'error', 7000);
  } finally {
    setBusy(false);
  }
});

localize();
elements.version.textContent = t('versionLabel', chrome.runtime.getManifest().version);
Promise.all([loadSettings(), updatePageAccess()]).catch((error) => {
  showStatus(error.message, 'error', 7000);
});
