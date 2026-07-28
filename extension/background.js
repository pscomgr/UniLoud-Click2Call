// UniLoud Click-to-Call Public v1.4.0 — Manifest V3 service worker.
importScripts('lib/core.js', 'lib/phone.js');

const LOCAL_KEYS = [
  'pbxUrl',
  'apiId',
  'apiSecret',
  'rememberSecret',
  'protocol',
  'myExtension',
  'sendPageContext'
];
const SESSION_KEYS = ['apiSecret'];
const REQUEST_TIMEOUT_MS = 15000;
const DIRECTORY_TIMEOUT_MS = 15000;
const CONTENT_SCRIPT_ID = 'uniloud-c2c-page-integration';
const PAGE_ORIGINS = ['https://*/*', 'http://*/*'];

function t(key, substitutions) {
  return chrome.i18n.getMessage(key, substitutions) || key;
}

async function lockStorage() {
  const tasks = [];
  if (chrome.storage.local?.setAccessLevel) {
    tasks.push(chrome.storage.local.setAccessLevel({ accessLevel: 'TRUSTED_CONTEXTS' }));
  }
  if (chrome.storage.session?.setAccessLevel) {
    tasks.push(chrome.storage.session.setAccessLevel({ accessLevel: 'TRUSTED_CONTEXTS' }));
  }
  await Promise.allSettled(tasks);
}

async function migrateSettings() {
  const legacy = await chrome.storage.local.get(['extList', 'pageAccessEnabled']);
  const updates = {};
  if (legacy.extList !== undefined) {
    updates.protocol = (await chrome.storage.local.get('protocol')).protocol || 'pjsip';
  }
  if (Object.keys(updates).length) {
    await chrome.storage.local.set(updates);
  }
  await chrome.storage.local.remove(['extList', 'pageAccessEnabled']);
}

async function ensureContextMenu() {
  await new Promise((resolve) => chrome.contextMenus.removeAll(resolve));
  chrome.contextMenus.create({
    id: 'uniloud-c2c-call-selection',
    title: t('contextMenuCall'),
    contexts: ['selection']
  });
}

async function hasPageAccess() {
  return chrome.permissions.contains({ origins: PAGE_ORIGINS });
}

async function syncContentScript() {
  const registered = await chrome.scripting.getRegisteredContentScripts({
    ids: [CONTENT_SCRIPT_ID]
  });
  const permitted = await hasPageAccess();
  if (permitted && registered.length === 0) {
    await chrome.scripting.registerContentScripts([{
      id: CONTENT_SCRIPT_ID,
      matches: PAGE_ORIGINS,
      js: ['lib/phone.js', 'content.js'],
      runAt: 'document_idle',
      allFrames: true,
      persistAcrossSessions: true
    }]);
  } else if (!permitted && registered.length > 0) {
    await chrome.scripting.unregisterContentScripts({ ids: [CONTENT_SCRIPT_ID] });
  }
  return permitted;
}

chrome.runtime.onInstalled.addListener(async (details) => {
  await lockStorage();
  await migrateSettings();
  await ensureContextMenu();
  await syncContentScript();
  if (details.reason === 'install') {
    await chrome.tabs.create({ url: chrome.runtime.getURL('about.html?welcome=1') });
  }
});

chrome.runtime.onStartup.addListener(async () => {
  await lockStorage();
  await ensureContextMenu();
  await syncContentScript();
});

chrome.permissions.onAdded.addListener(() => void syncContentScript());
chrome.permissions.onRemoved.addListener(() => void syncContentScript());
void lockStorage();

async function getSettings() {
  const [local, session] = await Promise.all([
    chrome.storage.local.get(LOCAL_KEYS),
    chrome.storage.session.get(SESSION_KEYS)
  ]);
  return {
    ...local,
    apiSecret: session.apiSecret || local.apiSecret || ''
  };
}

async function publicSettings() {
  const settings = await getSettings();
  return {
    ...settings,
    apiSecret: settings.apiSecret,
    hasSecret: Boolean(settings.apiSecret)
  };
}

async function saveSettings(input) {
  const pbxUrl = C2CCore.normalizePbxUrl(input.pbxUrl);
  const apiId = String(input.apiId || '').trim();
  const apiSecret = String(input.apiSecret || '');
  const protocol = String(input.protocol || '').toLowerCase();
  const myExtension = String(input.myExtension || '').trim();
  const rememberSecret = input.rememberSecret === true;
  if (!/^[A-Za-z0-9_.-]{1,64}$/.test(apiId)
      || apiSecret.length < 24
      || !['pjsip', 'sip'].includes(protocol)
      || !/^\d{2,8}$/.test(myExtension)) {
    throw new Error('INVALID_SETTINGS');
  }
  const local = {
    pbxUrl,
    apiId,
    protocol,
    myExtension,
    rememberSecret,
    sendPageContext: input.sendPageContext === true
  };
  if (rememberSecret) {
    local.apiSecret = apiSecret;
    await chrome.storage.session.remove('apiSecret');
  } else {
    local.apiSecret = '';
    await chrome.storage.session.set({ apiSecret });
  }
  await chrome.storage.local.set(local);
  return { success: true };
}

async function clearSettings() {
  await Promise.all([
    chrome.storage.local.remove(LOCAL_KEYS),
    chrome.storage.session.remove(SESSION_KEYS)
  ]);
  return { success: true };
}

function notify(title, message) {
  chrome.notifications.create({
    type: 'basic',
    iconUrl: 'icons/icon128.png',
    title,
    message
  }, () => void chrome.runtime.lastError);
}

async function postJson(endpoint, body, timeoutMs) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      cache: 'no-store',
      credentials: 'omit',
      redirect: 'error',
      referrerPolicy: 'no-referrer',
      signal: controller.signal,
      body: JSON.stringify(body)
    });
    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (_error) {
      return { success: false, error: t('errorInvalidResponse', String(response.status)) };
    }
    if (!response.ok || data.success !== true) {
      const suffix = data.requestId ? ` [${data.requestId}]` : '';
      return {
        success: false,
        error: `${data.error || `HTTP ${response.status}`}${suffix}`
      };
    }
    return data;
  } catch (error) {
    if (error.name === 'AbortError') {
      return { success: false, error: t('errorTimeout') };
    }
    return { success: false, error: t('errorConnection', error.message) };
  } finally {
    clearTimeout(timeout);
  }
}

function optionalPageContext(settings, message, sender) {
  if (settings.sendPageContext !== true) return {};
  const url = C2CCore.sanitizePageUrl(sender?.tab?.url || message.pageUrl);
  return url ? { pageUrl: url } : {};
}

async function callWithSettings(destinationValue, message = {}, sender = {}) {
  const settings = await getSettings();
  if (!settings.pbxUrl || !settings.apiId || !settings.apiSecret
      || !settings.myExtension || !['pjsip', 'sip'].includes(settings.protocol)) {
    return { success: false, error: t('errorNotConfigured') };
  }
  const destination = C2CCore.normalizeDestination(destinationValue);
  if (!destination) {
    return { success: false, error: t('errorInvalidNumber') };
  }
  const endpoint = C2CCore.buildEndpoint(settings.pbxUrl, 'originate_call.php');
  return postJson(endpoint, {
    apiId: settings.apiId,
    apiSecret: settings.apiSecret,
    extension: settings.myExtension,
    protocol: settings.protocol,
    destination,
    clientVersion: chrome.runtime.getManifest().version,
    ...optionalPageContext(settings, message, sender)
  }, REQUEST_TIMEOUT_MS);
}

async function listExtensions(connection) {
  const pbxUrl = C2CCore.normalizePbxUrl(connection.pbxUrl);
  const endpoint = C2CCore.buildEndpoint(pbxUrl, 'list_extensions.php');
  const result = await postJson(endpoint, {
    apiId: String(connection.apiId || '').trim(),
    apiSecret: String(connection.apiSecret || ''),
    protocol: String(connection.protocol || '').toLowerCase(),
    clientVersion: chrome.runtime.getManifest().version
  }, DIRECTORY_TIMEOUT_MS);
  if (!result.success) return result;
  if (!Array.isArray(result.extensions)) {
    return { success: false, error: t('errorInvalidDirectory') };
  }
  return result;
}

function trustedSender(sender) {
  return sender?.id === chrome.runtime.id;
}

function extensionErrorMessage(error) {
  const key = {
    HTTPS_REQUIRED: 'errorHttpsRequired',
    INVALID_URL: 'errorInvalidUrl',
    INVALID_SETTINGS: 'errorInvalidSettings'
  }[error?.message];
  return key ? t(key) : t('errorUnexpected');
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  if (!trustedSender(sender) || !message || typeof message.type !== 'string') {
    return false;
  }
  let operation;
  switch (message.type) {
    case 'CLICK_TO_CALL':
      operation = callWithSettings(message.destination, message, sender);
      break;
    case 'LIST_EXTENSIONS':
      operation = listExtensions(message.connection || {});
      break;
    case 'GET_SETTINGS':
      operation = publicSettings().then((settings) => ({ success: true, settings }));
      break;
    case 'SAVE_SETTINGS':
      operation = saveSettings(message.settings || {});
      break;
    case 'CLEAR_SETTINGS':
      operation = clearSettings();
      break;
    case 'PAGE_ACCESS_STATUS':
      operation = syncContentScript().then((enabled) => ({ success: true, enabled }));
      break;
    case 'SYNC_PAGE_ACCESS':
      operation = syncContentScript().then((enabled) => ({ success: true, enabled }));
      break;
    default:
      return false;
  }
  operation
    .then(sendResponse)
    .catch((error) => sendResponse({
      success: false,
      error: extensionErrorMessage(error)
    }));
  return true;
});

chrome.contextMenus.onClicked.addListener(async (info, tab) => {
  if (info.menuItemId !== 'uniloud-c2c-call-selection') return;
  const destination = C2CPhone.normalizedCandidate(info.selectionText || '')
    || C2CCore.normalizeDestination(info.selectionText || '');
  const result = await callWithSettings(
    destination || '',
    { pageUrl: info.pageUrl || '' },
    { tab }
  );
  notify(
    result.success ? t('notificationQueuedTitle') : t('notificationErrorTitle'),
    result.success ? (result.message || t('notificationQueued')) : result.error
  );
});
