/**
 * Arqel DevTools — DevTools page boot script.
 *
 * Registers the "Arqel" tab in the browser DevTools.
 */
if (typeof chrome !== 'undefined' && chrome.devtools?.panels) {
  chrome.devtools.panels.create('Arqel', '/icons/icon-32.png', '/panel.html');
}
