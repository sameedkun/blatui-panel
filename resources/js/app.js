import { registerBlatUI } from './blatui-core.js';

document.addEventListener('alpine:init', () => {
    registerBlatUI(window.Alpine, { darkMode: 'system' });
});
