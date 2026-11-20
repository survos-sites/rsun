import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    keydown(event) {
        if (event.target.name !== 'question' || event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229) return;
        event.preventDefault();
        const form = event.target.form;
        if (!form.querySelector('button[type="submit"]:disabled, button:not([type]):disabled')) form.requestSubmit();
    }
}
