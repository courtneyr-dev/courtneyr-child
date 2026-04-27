/* No-flash theme initializer.
 * MUST run synchronously in <head> before any stylesheet loads.
 * Reads stored theme preference and applies data-theme to <html>. */
(function () {
	try {
		var stored = localStorage.getItem('courtneyr-theme');
		if (stored === 'light' || stored === 'dark' || stored === 'system') {
			document.documentElement.setAttribute('data-theme', stored);
		} else {
			document.documentElement.setAttribute('data-theme', 'system');
		}
	} catch (e) {
		document.documentElement.setAttribute('data-theme', 'system');
	}
})();
