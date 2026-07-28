// Optional page integration. Registered only after explicit host permission.
(() => {
  'use strict';

  const WRAPPER_CLASS = 'uniloud-c2c-phone';
  const SKIP_TAGS = new Set([
    'SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'SELECT', 'OPTION',
    'A', 'BUTTON', 'CODE', 'PRE', 'SVG', 'CANVAS', 'VIDEO', 'AUDIO'
  ]);

  function t(key) {
    return chrome.i18n.getMessage(key) || key;
  }

  function toast(message, error = false) {
    document.getElementById('__uniloud_c2c_toast')?.remove();
    const element = document.createElement('div');
    element.id = '__uniloud_c2c_toast';
    element.textContent = message;
    element.setAttribute('role', 'status');
    element.style.cssText = [
      'position:fixed',
      'right:20px',
      'bottom:20px',
      'z-index:2147483647',
      `background:${error ? '#b91c1c' : '#116466'}`,
      'color:#fff',
      'padding:10px 14px',
      'border-radius:8px',
      'font:13px/1.35 system-ui,sans-serif',
      'box-shadow:0 6px 22px rgba(0,0,0,.28)',
      'max-width:420px'
    ].join(';');
    document.documentElement.appendChild(element);
    setTimeout(() => element.remove(), 4500);
  }

  function placeCall(destination) {
    if (!destination) {
      toast(t('contentInvalidNumber'), true);
      return;
    }
    chrome.runtime.sendMessage({
      type: 'CLICK_TO_CALL',
      destination,
      pageUrl: window.location.href
    }, (response) => {
      if (chrome.runtime.lastError) {
        toast(t('contentUpdated'), true);
      } else if (response?.success) {
        toast(response.message || t('contentQueued'));
      } else {
        toast(response?.error || t('contentFailed'), true);
      }
    });
  }

  function closest(target, selector) {
    if (target instanceof Element) return target.closest(selector);
    return target?.parentElement?.closest(selector) || null;
  }

  document.addEventListener('click', (event) => {
    const tel = closest(event.target, 'a[href^="tel:" i]');
    if (tel) {
      event.preventDefault();
      event.stopImmediatePropagation();
      placeCall(C2CPhone.normalizedCandidate(
        String(tel.getAttribute('href') || '').replace(/^tel:/i, '').split(/[;,]/, 1)[0]
      ));
      return;
    }
    const wrapped = closest(event.target, `.${WRAPPER_CLASS}`);
    if (wrapped) {
      event.preventDefault();
      event.stopImmediatePropagation();
      placeCall(wrapped.dataset.uniloudC2c || '');
    }
  }, true);

  function skip(element) {
    return !element
      || SKIP_TAGS.has(element.tagName)
      || element.isContentEditable
      || Boolean(element.closest(`.${WRAPPER_CLASS}`));
  }

  function wrapTextNode(node) {
    if (!node.parentNode || skip(node.parentElement)) return;
    const text = node.nodeValue || '';
    const matches = C2CPhone.findCandidates(text);
    if (!matches.length) return;
    const fragment = document.createDocumentFragment();
    let cursor = 0;
    for (const match of matches) {
      if (match.index > cursor) {
        fragment.append(document.createTextNode(text.slice(cursor, match.index)));
      }
      const span = document.createElement('span');
      span.className = WRAPPER_CLASS;
      span.dataset.uniloudC2c = match.normalized;
      span.textContent = match.text;
      span.title = t('contentClickTitle');
      span.tabIndex = 0;
      span.style.cssText = 'color:#116466;cursor:pointer;text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:2px';
      span.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          placeCall(match.normalized);
        }
      });
      fragment.append(span);
      cursor = match.index + match.text.length;
    }
    if (cursor < text.length) {
      fragment.append(document.createTextNode(text.slice(cursor)));
    }
    node.parentNode.replaceChild(fragment, node);
  }

  function scan(root) {
    if (!root) return;
    if (root.nodeType === Node.TEXT_NODE) {
      wrapTextNode(root);
      return;
    }
    if (!(root instanceof Element || root instanceof Document || root instanceof DocumentFragment)) {
      return;
    }
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        return node.nodeValue?.trim() && !skip(node.parentElement)
          ? NodeFilter.FILTER_ACCEPT
          : NodeFilter.FILTER_REJECT;
      }
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(wrapTextNode);
  }

  scan(document.body);
  const pending = new Set();
  let timer = null;
  const observer = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
      if (mutation.type === 'characterData') pending.add(mutation.target);
      mutation.addedNodes.forEach((node) => pending.add(node));
    }
    if (pending.size && timer === null) {
      timer = setTimeout(() => {
        timer = null;
        const roots = [...pending];
        pending.clear();
        roots.forEach(scan);
      }, 200);
    }
  });
  observer.observe(document.body, {
    childList: true,
    subtree: true,
    characterData: true
  });
})();
