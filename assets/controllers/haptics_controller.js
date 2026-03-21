import { Controller } from '@hotwired/stimulus';
import { WebHaptics } from 'web-haptics';

export default class extends Controller {
    static values = {
        pattern: { type: String, default: 'selection' },
    }

    connect() {
        this.haptics = new WebHaptics();
    }

    trigger(event) {
        const pattern = event.params?.pattern ?? this.patternValue;
        this.haptics.trigger(pattern);
    }
}
