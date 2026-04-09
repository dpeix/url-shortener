import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['list', 'count', 'empty'];
    static values = { apiUrl: String };

    connect() {
        document.addEventListener('url-shortener:created', (event) => this.#prependUrl(event.detail));
    }

    disconnect() {
        document.removeEventListener('url-shortener:created', (event) => this.#prependUrl(event.detail));
    }

    async copy(event) {
        const button = event.currentTarget;
        const shortCode = button.dataset.shortCode;
        const shortUrl = `${window.location.origin}/${shortCode}`;

        await navigator.clipboard.writeText(shortUrl);
        button.textContent = '✅';
        setTimeout(() => { button.textContent = '📋'; }, 2000);
    }

    #prependUrl(data) {
        const isExpired = data.expiresAt && new Date(data.expiresAt) < new Date();
        const expiresLabel = data.expiresAt
            ? `<span>Expire le ${new Date(data.expiresAt).toLocaleDateString('fr-FR')}</span>`
            : '';

        const card = document.createElement('div');
        card.className = 'card shadow-sm';
        card.dataset.urlId = data.id;
        card.innerHTML = `
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <a href="/${data.shortCode}" class="fw-bold text-dark text-decoration-none" target="_blank">
                                /${data.shortCode}
                            </a>
                            <span class="badge bg-success">Actif</span>
                        </div>
                        <div class="text-muted small text-truncate" style="max-width: 400px" title="${this.#escapeHtml(data.originalUrl)}">
                            ${this.#escapeHtml(data.originalUrl)}
                        </div>
                        <div class="d-flex gap-3 mt-2 text-muted" style="font-size: .8rem">
                            <span>0 clic</span>
                            <span>Créé le ${new Date(data.createdAt).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                            ${expiresLabel}
                        </div>
                    </div>
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm"
                        data-action="click->url-list#copy"
                        data-short-code="${data.shortCode}"
                        title="Copier le lien"
                    >📋</button>
                </div>
            </div>
        `;

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.add('d-none');
        }

        this.listTarget.prepend(card);
        this.#updateCount(1);
    }

    #updateCount(delta) {
        if (this.hasCountTarget) {
            this.countTarget.textContent = parseInt(this.countTarget.textContent || '0') + delta;
        }
    }

    #escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
}
