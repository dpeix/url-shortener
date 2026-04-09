import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'inputError', 'expiresAt', 'submitButton', 'spinner', 'result', 'shortLink', 'copyButton', 'errorAlert', 'errorMessage'];
    static values = { createUrl: String };

    async create(event) {
        event.preventDefault();

        const originalUrl = this.inputTarget.value.trim();
        if (!originalUrl) {
            this.#showInputError('Veuillez entrer une URL.');
            return;
        }

        this.#setLoading(true);
        this.#hideAlerts();

        const body = { originalUrl };
        const expiresAt = this.expiresAtTarget.value;
        if (expiresAt) {
            body.expiresAt = new Date(expiresAt).toISOString();
        }

        try {
            const response = await fetch(this.createUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });

            const data = await response.json();

            if (!response.ok) {
                this.#showError(data.detail ?? data.message ?? 'Une erreur est survenue.');
                return;
            }

            const shortUrl = `${window.location.origin}/${data.shortCode}`;
            this.shortLinkTarget.href = shortUrl;
            this.shortLinkTarget.textContent = shortUrl;
            this.resultTarget.classList.remove('d-none');

            this.#resetForm();
            this.dispatch('created', { detail: data });
        } catch {
            this.#showError('Impossible de contacter le serveur.');
        } finally {
            this.#setLoading(false);
        }
    }

    async copy() {
        const url = this.shortLinkTarget.href;
        await navigator.clipboard.writeText(url);
        this.copyButtonTarget.textContent = 'Copié !';
        setTimeout(() => { this.copyButtonTarget.textContent = 'Copier'; }, 2000);
    }

    #setLoading(loading) {
        this.submitButtonTarget.disabled = loading;
        this.spinnerTarget.classList.toggle('d-none', !loading);
    }

    #hideAlerts() {
        this.resultTarget.classList.add('d-none');
        this.errorAlertTarget.classList.add('d-none');
        this.inputTarget.classList.remove('is-invalid');
    }

    #showInputError(message) {
        this.inputTarget.classList.add('is-invalid');
        this.inputErrorTarget.textContent = message;
    }

    #showError(message) {
        this.errorAlertTarget.classList.remove('d-none');
        this.errorMessageTarget.textContent = message;
    }

    #resetForm() {
        this.inputTarget.value = '';
        this.expiresAtTarget.value = '';
    }
}
