// any CSS you import will output into a single css file (app.scss in this case)
import './styles/app.scss';

const $ = require('jquery');
require('bootstrap');
$(document).ready(function() {
    $('[data-toggle="popover"]').popover();
});

// start the Stimulus application
import './bootstrap';