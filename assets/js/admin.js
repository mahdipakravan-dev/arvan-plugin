(() => {
	'use strict';
	const form = document.querySelector('.acpn-actions form');
	if (!form) return;
	form.addEventListener('submit', () => {
		const button = form.querySelector('button[type="submit"]');
		if (!button) return;
		button.disabled = true;
		button.setAttribute('aria-busy', 'true');
	});
})();

