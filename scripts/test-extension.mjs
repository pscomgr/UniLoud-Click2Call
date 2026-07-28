import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const extension = path.join(root, 'extension');

function loadLibrary(relativePath, globalName) {
  const context = vm.createContext({ URL });
  vm.runInContext(
    fs.readFileSync(path.join(extension, relativePath), 'utf8'),
    context,
    { filename: relativePath }
  );
  return context[globalName];
}

const core = loadLibrary('lib/core.js', 'C2CCore');
const phone = loadLibrary('lib/phone.js', 'C2CPhone');

assert.equal(
  core.normalizePbxUrl(' https://pbx.example.com/c2c/originate_call.php?q=x#y '),
  'https://pbx.example.com/c2c'
);
assert.throws(() => core.normalizePbxUrl('not a URL'), /INVALID_URL/);
assert.throws(() => core.normalizePbxUrl('http://pbx.example.com/c2c'), /HTTPS_REQUIRED/);
assert.equal(
  core.endpointPermission('https://pbx.example.com:8443/c2c'),
  'https://pbx.example.com:8443/*'
);
assert.equal(
  core.buildEndpoint('https://pbx.example.com/c2c', 'list_extensions.php'),
  'https://pbx.example.com/c2c/list_extensions.php'
);
assert.throws(
  () => core.buildEndpoint('https://pbx.example.com/c2c', '../admin.php'),
  /INVALID_ENDPOINT/
);
assert.equal(
  core.sanitizePageUrl('https://user:pass@example.com/ticket/1?q=secret#x'),
  'https://example.com/ticket/1'
);
assert.equal(core.sanitizePageUrl('chrome://settings'), null);
assert.equal(core.normalizeDestination('+30 (210) 756-3001'), '+302107563001');
assert.equal(core.normalizeDestination('1'), null);

assert.equal(phone.normalizedCandidate('+30 210 756 3001'), '+302107563001');
assert.equal(phone.normalizedCandidate('12345'), null);
const candidates = phone.findCandidates('Call +30 210 756 3001 or 694 123 4567.');
assert.equal(candidates.length, 2);
assert.equal(candidates[0].normalized, '+302107563001');

const manifest = JSON.parse(
  fs.readFileSync(path.join(extension, 'manifest.json'), 'utf8')
);
assert.equal(manifest.manifest_version, 3);
assert.equal(manifest.version, '1.4.0');
assert.equal(manifest.minimum_chrome_version, '102');
assert.equal(manifest.host_permissions, undefined);
assert.equal(manifest.content_scripts, undefined);
assert.deepEqual(
  [...manifest.permissions].sort(),
  ['contextMenus', 'notifications', 'scripting', 'storage'].sort()
);
assert.deepEqual(
  [...manifest.optional_host_permissions].sort(),
  ['http://*/*', 'https://*/*'].sort()
);

const requiredFiles = [
  manifest.background.service_worker,
  manifest.action.default_popup,
  ...Object.values(manifest.icons),
  ...Object.values(manifest.action.default_icon)
];
for (const relativePath of requiredFiles) {
  assert.ok(
    fs.existsSync(path.join(extension, relativePath))
      && fs.statSync(path.join(extension, relativePath)).isFile(),
    `missing ${relativePath}`
  );
}

const locales = {};
for (const locale of ['en', 'el']) {
  locales[locale] = JSON.parse(
    fs.readFileSync(path.join(extension, '_locales', locale, 'messages.json'), 'utf8')
  );
}
assert.deepEqual(
  Object.keys(locales.en).sort(),
  Object.keys(locales.el).sort(),
  'locale keys differ'
);

const textFiles = [
  'manifest.json',
  'background.js',
  'content.js',
  'popup.js',
  'popup.html',
  'about.js',
  'about.html'
];
const referencedKeys = new Set();
for (const relativePath of textFiles) {
  const source = fs.readFileSync(path.join(extension, relativePath), 'utf8');
  for (const match of source.matchAll(/\bt\(\s*['"]([^'"]+)['"]/g)) {
    referencedKeys.add(match[1]);
  }
  for (const match of source.matchAll(/data-i18n(?:-placeholder)?="([^"]+)"/g)) {
    referencedKeys.add(match[1]);
  }
  for (const match of source.matchAll(/__MSG_([A-Za-z0-9_]+)__/g)) {
    referencedKeys.add(match[1]);
  }
  for (const match of source.matchAll(/<(?:script|link)\b[^>]+(?:src|href)="([^"]+)"/gi)) {
    assert.ok(!/^https?:/i.test(match[1]), `remote resource in ${relativePath}`);
  }
}
for (const key of referencedKeys) {
  assert.ok(locales.en[key], `missing English locale key ${key}`);
  assert.ok(locales.el[key], `missing Greek locale key ${key}`);
}

console.log(
  `PASS extension tests: ${referencedKeys.size} locale keys, MV3 least-privilege manifest`
);
