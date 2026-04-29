import 'bootstrap';
import Alpine from 'alpinejs';
import registerAlpineComponents from './alpine_components.js';
import 'haptics';
import './csrf_protection.js';

registerAlpineComponents(Alpine);
window.Alpine = Alpine;
Alpine.start();
