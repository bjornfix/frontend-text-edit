(function () {
	'use strict';

	const config = window.FrontendTextEdit;
	if (!config || !config.postId || !config.endpoint || !config.nonce) {
		return;
	}

	const labels = config.labels || {};
	const editableSelector = '[data-frontend-text-edit-path][data-frontend-text-edit-hash]';
	let active = false;
	let toolbar;
	let statusNode;
	let reportPrompt;
	let reportTarget;
	let currentElement;

	function label(name, fallback) {
		return labels[name] || fallback;
	}

	function elements() {
		return Array.from(document.querySelectorAll(editableSelector));
	}

	function requestSave(element, nextText) {
		return fetch(config.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify({
				post_id: config.postId,
				path: element.dataset.frontendTextEditPath,
				hash: element.dataset.frontendTextEditHash,
				text: nextText
			})
		}).then((response) => response.json());
	}

	function requestReport(element) {
		return fetch(config.reportEndpoint, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify({
				post_id: config.postId,
				text: editableText(element),
				selector: selectorFor(element),
				url: window.location.href
			})
		}).then((response) => response.json());
	}

	function toggle() {
		active = !active;
		document.body.classList.toggle('frontend-text-edit-active', active);
		if (active) {
			enable();
			showStatus(label('active', 'Click text to edit it inline.'), 'info');
		} else {
			disable();
			showStatus(label('inactive', 'Frontend Text Edit is off.'), 'info');
		}
	}

	function enable() {
		elements().forEach((element) => {
			element.dataset.frontendTextEditOriginal = editableText(element);
			element.setAttribute('contenteditable', 'plaintext-only');
			element.setAttribute('spellcheck', 'true');
			element.setAttribute('role', 'textbox');
			element.setAttribute('aria-label', element.dataset.frontendTextEditLabel || label('open', 'Frontend Text Edit'));
			element.addEventListener('focus', handleFocus);
			element.addEventListener('input', handleInput);
			element.addEventListener('keydown', handleKeydown);
			element.addEventListener('click', preventEditableLinkNavigation);
		});
	}

	function disable() {
		hideToolbar();
		hideReportPrompt();
		elements().forEach((element) => {
			if (element.dataset.frontendTextEditOriginal && element.classList.contains('frontend-text-edit-dirty')) {
				element.textContent = element.dataset.frontendTextEditOriginal;
			}
			element.removeAttribute('contenteditable');
			element.removeAttribute('spellcheck');
			element.removeAttribute('role');
			element.removeAttribute('aria-label');
			element.classList.remove('frontend-text-edit-dirty', 'frontend-text-edit-saving');
			element.removeEventListener('focus', handleFocus);
			element.removeEventListener('input', handleInput);
			element.removeEventListener('keydown', handleKeydown);
			element.removeEventListener('click', preventEditableLinkNavigation);
		});
		currentElement = null;
	}

	function handleFocus(event) {
		currentElement = event.currentTarget;
		positionToolbar(currentElement);
	}

	function handleInput(event) {
		const element = event.currentTarget;
		const dirty = editableText(element).trim() !== (element.dataset.frontendTextEditOriginal || '').trim();
		element.classList.toggle('frontend-text-edit-dirty', dirty);
		positionToolbar(element);
	}

	function handleKeydown(event) {
		if (event.key === 'Escape') {
			event.preventDefault();
			cancelEdit(event.currentTarget);
			return;
		}
		if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
			event.preventDefault();
			saveElement(event.currentTarget);
		}
	}

	function preventEditableLinkNavigation(event) {
		if (active && event.currentTarget.tagName.toLowerCase() === 'a') {
			event.preventDefault();
		}
	}

	function editableText(element) {
		return (element.innerText || element.textContent || '').replace(/\s+/g, ' ').trim();
	}

	function ensureToolbar() {
		if (toolbar) {
			return;
		}

		toolbar = document.createElement('div');
		toolbar.className = 'frontend-text-edit-toolbar';
		toolbar.hidden = true;
		toolbar.innerHTML = `
			<button type="button" class="frontend-text-edit-toolbar__save">${escapeHtml(label('save', 'Save'))}</button>
			<button type="button" class="frontend-text-edit-toolbar__cancel">${escapeHtml(label('cancel', 'Cancel'))}</button>
		`;
		statusNode = document.createElement('div');
		statusNode.className = 'frontend-text-edit-status';
		statusNode.setAttribute('aria-live', 'polite');
		document.body.appendChild(toolbar);
		document.body.appendChild(statusNode);
		toolbar.querySelector('.frontend-text-edit-toolbar__save').addEventListener('click', () => {
			if (currentElement) {
				saveElement(currentElement);
			}
		});
		toolbar.querySelector('.frontend-text-edit-toolbar__cancel').addEventListener('click', () => {
			if (currentElement) {
				cancelEdit(currentElement);
			}
			});
	}

	function ensureReportPrompt() {
		if (reportPrompt) {
			return;
		}

		reportPrompt = document.createElement('div');
		reportPrompt.className = 'frontend-text-edit-report';
		reportPrompt.hidden = true;
		reportPrompt.innerHTML = `
			<div class="frontend-text-edit-report__hint">${escapeHtml(label('reportHint', 'This text is not editable yet.'))}</div>
			<button type="button" class="frontend-text-edit-report__send">${escapeHtml(label('report', 'Send report'))}</button>
			<button type="button" class="frontend-text-edit-report__cancel">${escapeHtml(label('cancel', 'Cancel'))}</button>
		`;
		document.body.appendChild(reportPrompt);
		reportPrompt.querySelector('.frontend-text-edit-report__send').addEventListener('click', () => {
			if (reportTarget) {
				sendReport(reportTarget);
			}
		});
		reportPrompt.querySelector('.frontend-text-edit-report__cancel').addEventListener('click', hideReportPrompt);
	}

	function positionToolbar(element) {
		ensureToolbar();
		currentElement = element;
		const rect = element.getBoundingClientRect();
		toolbar.hidden = false;
		const top = Math.max(42, rect.top + window.scrollY - toolbar.offsetHeight - 8);
		const left = Math.min(
			window.scrollX + document.documentElement.clientWidth - toolbar.offsetWidth - 12,
			Math.max(window.scrollX + 12, rect.left + window.scrollX)
		);
		toolbar.style.top = `${top}px`;
		toolbar.style.left = `${left}px`;
	}

	function hideToolbar() {
		if (toolbar) {
			toolbar.hidden = true;
		}
	}

	function showReportPrompt(element) {
		ensureReportPrompt();
		reportTarget = element;
		const rect = element.getBoundingClientRect();
		reportPrompt.hidden = false;
		const top = Math.max(42, rect.top + window.scrollY - reportPrompt.offsetHeight - 8);
		const left = Math.min(
			window.scrollX + document.documentElement.clientWidth - reportPrompt.offsetWidth - 12,
			Math.max(window.scrollX + 12, rect.left + window.scrollX)
		);
		reportPrompt.style.top = `${top}px`;
		reportPrompt.style.left = `${left}px`;
	}

	function hideReportPrompt() {
		if (reportPrompt) {
			reportPrompt.hidden = true;
		}
		reportTarget = null;
	}

	function cancelEdit(element) {
		element.textContent = element.dataset.frontendTextEditOriginal || '';
		element.classList.remove('frontend-text-edit-dirty');
		element.blur();
		hideToolbar();
		showStatus(label('unchanged', 'No text change to save.'), 'info');
	}

	function saveElement(element) {
		const next = editableText(element);
		const original = (element.dataset.frontendTextEditOriginal || '').trim();
		if (next === original) {
			showStatus(label('unchanged', 'No text change to save.'), 'info');
			return;
		}

		element.classList.add('frontend-text-edit-saving');
		requestSave(element, next).then((data) => {
			element.classList.remove('frontend-text-edit-saving');
			if (!data || !data.success) {
				showStatus((data && data.message) || label('error', 'Could not save this text change.'), 'error');
				return;
			}
			if (data.item && data.item.hash) {
				element.dataset.frontendTextEditHash = data.item.hash;
			}
			element.dataset.frontendTextEditOriginal = next;
			element.classList.remove('frontend-text-edit-dirty');
			element.blur();
			hideToolbar();
			showStatus(label('saved', 'Saved.'), 'success');
		}).catch(() => {
			element.classList.remove('frontend-text-edit-saving');
			showStatus(label('error', 'Could not save this text change.'), 'error');
		});
	}

	function sendReport(element) {
		const text = editableText(element);
		if (!text) {
			showStatus(label('reportEmpty', 'No readable text found to report.'), 'error');
			hideReportPrompt();
			return;
		}

		element.classList.add('frontend-text-edit-reporting');
		requestReport(element).then((data) => {
			element.classList.remove('frontend-text-edit-reporting');
			if (!data || !data.success) {
				showStatus((data && data.message) || label('reportError', 'Could not send this report.'), 'error');
				return;
			}
			hideReportPrompt();
			showStatus(label('reportSaved', 'Report sent.'), 'success');
		}).catch(() => {
			element.classList.remove('frontend-text-edit-reporting');
			showStatus(label('reportError', 'Could not send this report.'), 'error');
		});
	}

	function showStatus(message, mode) {
		ensureToolbar();
		statusNode.textContent = message || '';
		statusNode.dataset.mode = mode || 'info';
		statusNode.hidden = !message;
		window.clearTimeout(statusNode._frontendTextEditTimer);
		if (message) {
			statusNode._frontendTextEditTimer = window.setTimeout(() => {
				statusNode.hidden = true;
			}, 3000);
		}
	}

	function escapeHtml(value) {
		return String(value || '').replace(/[&<>"']/g, (char) => ({
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		}[char]));
	}

	function reportableElement(target) {
		if (!target || !target.closest) {
			return null;
		}
		if (target.closest('#wpadminbar, .frontend-text-edit-toolbar, .frontend-text-edit-report, [data-frontend-text-edit-path], input, textarea, select, button, iframe, video, audio, canvas, svg')) {
			return null;
		}

		const element = target.closest('p,h1,h2,h3,h4,h5,h6,li,a,blockquote,figcaption,summary,dt,dd,td,th,label,span,div');
		if (!element || !document.body.contains(element) || element.closest('[data-frontend-text-edit-path]')) {
			return null;
		}

		return editableText(element) ? element : null;
	}

	function selectorFor(element) {
		const parts = [];
		let node = element;
		while (node && node.nodeType === 1 && node !== document.body && parts.length < 5) {
			let part = node.tagName.toLowerCase();
			if (node.id && !node.id.match(/^frontend-text-edit/i)) {
				part += `#${cssEscape(node.id)}`;
				parts.unshift(part);
				break;
			}
			const classes = Array.from(node.classList || [])
				.filter((name) => !name.match(/^frontend-text-edit/))
				.slice(0, 2);
			if (classes.length) {
				part += `.${classes.map(cssEscape).join('.')}`;
			}
			parts.unshift(part);
			node = node.parentElement;
		}
		return parts.join(' > ');
	}

	function cssEscape(value) {
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(value);
		}
		return String(value || '').replace(/[^a-zA-Z0-9_-]/g, '\\$&');
	}

	document.addEventListener('click', (event) => {
		const target = event.target && event.target.closest ? event.target.closest('#wp-admin-bar-frontend-text-edit a') : null;
		if (!target) {
			return;
		}
		event.preventDefault();
		toggle();
	});

	document.addEventListener('click', (event) => {
		if (!active) {
			return;
		}
		const element = reportableElement(event.target);
		if (!element) {
			return;
		}
		event.preventDefault();
		showReportPrompt(element);
	});

	window.addEventListener('scroll', () => {
		if (active && currentElement && toolbar && !toolbar.hidden) {
			positionToolbar(currentElement);
		}
		if (active && reportTarget && reportPrompt && !reportPrompt.hidden) {
			showReportPrompt(reportTarget);
		}
	}, { passive: true });

	window.addEventListener('resize', () => {
		if (active && currentElement && toolbar && !toolbar.hidden) {
			positionToolbar(currentElement);
		}
		if (active && reportTarget && reportPrompt && !reportPrompt.hidden) {
			showReportPrompt(reportTarget);
		}
	});
}());
