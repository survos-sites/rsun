import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { name: String };

    switch(event) {
        const link = event.target.closest('a[data-search-name]');
        if (!link) return;
        const destination = new URL(link.href);
        destination.search = '';
        const source = `${this.nameValue}[`;
        for (const [key, value] of new URLSearchParams(window.location.search)) {
            if (!key.startsWith(source) || key === `${source}sortBy]` || key === `${source}page]`) continue;
            destination.searchParams.append(`${link.dataset.searchName}[${key.slice(source.length)}`, value);
        }
        link.href = destination.href;
    }
}
