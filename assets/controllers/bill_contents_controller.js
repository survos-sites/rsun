import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    follow(event) {
        const link = event.target.closest('a[href^="#"]');
        if (!link) return;
        this.element.hidePopover();
        const target = document.getElementById(link.hash.slice(1));
        if (target) {
            // Preserve native fragment navigation while moving keyboard focus into the text.
            target.tabIndex = -1;
            requestAnimationFrame(() => target.focus({ preventScroll: true }));
        }
    }
}
