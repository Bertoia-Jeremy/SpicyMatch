import 'bootstrap';
import Alpine from 'alpinejs';
import registerAlpineComponents from './alpine_components.js';
import 'haptics';

registerAlpineComponents(Alpine);
window.Alpine = Alpine;
Alpine.start();
