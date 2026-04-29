/* Symfony stateless CSRF — official recipe content
 * https://github.com/symfony/recipes/blob/main/symfony/framework-bundle/6.3/assets/controllers/csrf_protection_controller.js
 *
 * Listens globally for form submissions. If the form has a hidden
 * `input[data-controller*="csrf-protection"]` with `value="csrf-token"`, we
 * generate a fresh token, set it as the field value AND as a same-name cookie,
 * so Symfony's stateless CSRF validator (double-submit) can verify the match.
 */

const nameCheck = /^[-_a-zA-Z0-9]{4,22}$/;
const tokenCheck = /^[-_/+a-zA-Z0-9]{24,}$/;

// Generate and double-submit a CSRF token in a form's data-controller="csrf-protection" input.
const generateCsrfToken = (formElement) => {
    const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');

    if (!csrfField || !csrfField.value) {
        return;
    }

    let csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
    let csrfToken = csrfField.value;

    if (!csrfCookie && nameCheck.test(csrfToken)) {
        csrfField.setAttribute('data-csrf-protection-cookie-value', csrfCookie = csrfToken);
        csrfField.defaultValue = csrfToken = btoa(String.fromCharCode(...crypto.getRandomValues(new Uint8Array(18))));
        csrfField.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (csrfCookie && tokenCheck.test(csrfToken)) {
        const cookie = `${csrfCookie}_${csrfToken}=${csrfCookie}; path=/; samesite=strict`;
        document.cookie = window.location.protocol === 'https:' ? `__Host-${cookie}; secure` : cookie;
    }
};

document.addEventListener('submit', (event) => {
    generateCsrfToken(event.target);
}, true);

document.addEventListener('turbo:submit-start', (event) => {
    generateCsrfToken(event.detail.formSubmission.formElement);
});
