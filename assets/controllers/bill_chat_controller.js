import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['history'];
    connect() {
        if (this.hasHistoryTarget && this.historyTarget.querySelector('.mobile-message')) {
            requestAnimationFrame(() => { this.historyTarget.scrollTop = this.historyTarget.scrollHeight; });
        }
    }
    keydown(event) {
        if (event.target.name !== 'question' || event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) return;
        event.preventDefault();
        const form = event.target.form;
        if (!form.querySelector('button[type="submit"]:disabled, button:not([type]):disabled')) form.requestSubmit();
    }
}
