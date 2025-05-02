// any CSS you import will output into a single css file (app.scss in this case)
import './styles/app.scss';

require('bootstrap');

// start the Stimulus application
import './bootstrap';

import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();