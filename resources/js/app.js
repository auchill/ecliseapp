import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const alertClasses = {
    popup: 'eclise-alert-popup',
    confirmButton: 'btn btn-primary',
    cancelButton: 'btn btn-outline-secondary',
    denyButton: 'btn btn-outline-danger',
};

const fire = (options = {}) => Swal.fire({
    width: options.width || '32rem',
    buttonsStyling: false,
    focusConfirm: false,
    returnFocus: true,
    customClass: {
        ...alertClasses,
        ...(options.customClass || {}),
    },
    ...options,
});

const EcliseAlert = {
    success(message, title = 'Success') {
        return fire({ icon: 'success', title, text: message });
    },

    error(message, title = 'Unable to Complete Request') {
        return fire({ icon: 'error', title, text: message });
    },

    warning(message, title = 'Please Check') {
        return fire({ icon: 'warning', title, text: message });
    },

    info(message, title = 'Information') {
        return fire({ icon: 'info', title, text: message });
    },

    confirm(options = {}) {
        return fire({
            icon: options.icon || (options.danger ? 'warning' : 'question'),
            title: options.title || 'Confirm action',
            text: options.text || '',
            html: options.html,
            showCancelButton: true,
            confirmButtonText: options.confirmButtonText || 'Continue',
            cancelButtonText: options.cancelButtonText || 'Cancel',
            customClass: {
                ...alertClasses,
                confirmButton: options.danger ? 'btn btn-danger' : 'btn btn-primary',
            },
        });
    },

    toast(message, icon = 'success') {
        return Swal.fire({
            toast: true,
            position: 'top-end',
            icon,
            title: message,
            timer: 3200,
            timerProgressBar: true,
            showConfirmButton: false,
            showCloseButton: true,
            customClass: {
                popup: 'eclise-alert-popup',
            },
        });
    },
};

window.EcliseAlert = EcliseAlert;

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const firstError = (errors) => Object.values(errors || {}).flat().filter(Boolean)[0] || null;

const parseJsonResponse = async (response) => {
    const contentType = response.headers.get('content-type') || '';

    if (!contentType.includes('application/json')) {
        return null;
    }

    return response.json();
};

const formMethod = (form) => {
    const spoofedMethod = form.querySelector('input[name="_method"]')?.value;

    return (spoofedMethod || form.method || 'POST').toUpperCase();
};

const setPending = (form, pending) => {
    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
        if (pending) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Processing';
        } else {
            button.disabled = false;
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
                delete button.dataset.originalHtml;
            }
        }
    });
};

const fieldSelector = (name) => {
    if (window.CSS?.escape) {
        return `[name="${CSS.escape(name)}"]`;
    }

    return `[name="${String(name).replaceAll('"', '\\"')}"]`;
};

const clearValidationErrors = (form) => {
    form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
    form.querySelectorAll('[data-ajax-error]').forEach((error) => error.remove());
};

const applyValidationErrors = (form, errors) => {
    clearValidationErrors(form);

    Object.entries(errors || {}).forEach(([name, messages]) => {
        const field = form.querySelector(fieldSelector(name));

        if (!field) {
            return;
        }

        field.classList.add('is-invalid');

        let feedback = field.parentElement?.querySelector(`[data-ajax-error-for="${name}"]`);

        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback d-block';
            feedback.dataset.ajaxError = 'true';
            feedback.dataset.ajaxErrorFor = name;
            field.insertAdjacentElement('afterend', feedback);
        }

        feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
    });
};

const requestJson = async (form) => {
    const response = await fetch(form.action, {
        method: formMethod(form),
        body: new FormData(form),
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });

    const json = await parseJsonResponse(response);

    return { response, json };
};

const handleAuthFailure = async (json) => {
    const result = await EcliseAlert.confirm({
        icon: 'info',
        title: 'Customer access required',
        text: json?.message || 'Please sign in to continue.',
        confirmButtonText: 'Sign In',
        cancelButtonText: 'Cancel',
    });

    if (result.isConfirmed && json?.login_url) {
        window.location.href = json.login_url;
    }
};

const updateCartBadges = (count) => {
    if (count === undefined || count === null) {
        return;
    }

    document.querySelectorAll('.cart-action-badge').forEach((badge) => {
        badge.textContent = String(count);
    });
};

const renderEmptyCart = () => {
    const panel = document.querySelector('[data-cart-panel]');

    if (!panel) {
        return;
    }

    const shopUrl = panel.dataset.shopUrl || '/shop';

    panel.innerHTML = `
        <p class="muted">Your cart is empty.</p>
        <a class="btn btn-primary" href="${escapeHtml(shopUrl)}"><i class="bi bi-bag me-2" aria-hidden="true"></i>Shop Products</a>
    `;
};

const updateCartPage = (cart) => {
    if (!cart?.items) {
        return;
    }

    const items = new Map(cart.items.map((item) => [String(item.cart_key), item]));

    document.querySelectorAll('[data-cart-item-row]').forEach((row) => {
        const item = items.get(String(row.dataset.cartItemRow));

        if (!item) {
            row.remove();
            return;
        }

        const quantity = row.querySelector('[data-cart-quantity]');
        const unitPrice = row.querySelector('[data-cart-unit-price]');
        const lineTotal = row.querySelector('[data-cart-line-total]');

        if (quantity) {
            quantity.value = item.quantity;
            quantity.max = item.max_quantity;
        }

        if (unitPrice) {
            unitPrice.textContent = item.unit_price_display;
        }

        if (lineTotal) {
            lineTotal.textContent = item.line_total_display;
        }
    });

    document.querySelectorAll('[data-cart-subtotal]').forEach((subtotal) => {
        subtotal.textContent = cart.subtotal_display;
    });

    if (cart.count === 0) {
        renderEmptyCart();
    }
};

const resetBulkCartForm = (form) => {
    if (!form.matches('[data-cpo-bulk-cart]')) {
        return;
    }

    form.querySelectorAll('[data-cpo-qty-input]').forEach((input) => {
        input.value = '0';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
};

const bootFlashAlerts = () => {
    document.querySelectorAll('[data-eclise-flash]').forEach((element) => {
        let payload = null;

        try {
            payload = JSON.parse(element.dataset.messages || '{}');
        } catch {
            payload = null;
        }

        if (!payload) {
            return;
        }

        const validation = payload.validation || [];

        if (validation.length > 0) {
            const html = `<p>Please review the highlighted fields and try again.</p><ul class="text-start mb-0">${validation.map((message) => `<li>${escapeHtml(message)}</li>`).join('')}</ul>`;
            fire({
                icon: 'warning',
                title: 'Please Check',
                html,
            });
            return;
        }

        const flash = payload.flash || {};
        const priority = ['error', 'warning', 'info', 'success', 'status'].find((type) => flash[type]);

        if (!priority) {
            return;
        }

        const message = flash[priority];

        if (priority === 'error') {
            EcliseAlert.error(message);
        } else if (priority === 'warning') {
            EcliseAlert.warning(message);
        } else if (priority === 'info') {
            EcliseAlert.info(message);
        } else {
            EcliseAlert.success(message);
        }
    });
};

const bootContactForms = () => {
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-eclise-contact-form]');

        if (!form || form.dataset.pending === '1') {
            return;
        }

        event.preventDefault();
        form.dataset.pending = '1';
        setPending(form, true);
        clearValidationErrors(form);

        try {
            const { response, json } = await requestJson(form);

            if (response.ok) {
                form.reset();
                clearValidationErrors(form);
                await EcliseAlert.success(json?.message || 'Message sent. The Eclise team will respond soon.');
                return;
            }

            if (response.status === 422 && json?.errors) {
                applyValidationErrors(form, json.errors);
                await EcliseAlert.warning('Please review the highlighted fields and try again.');
                return;
            }

            if (response.status === 401) {
                await handleAuthFailure(json);
                return;
            }

            await EcliseAlert.error(json?.message || 'We could not complete your request at this time. Please try again.');
        } catch {
            await EcliseAlert.error('We could not connect to the server. Please check your connection and try again.');
        } finally {
            delete form.dataset.pending;
            setPending(form, false);
        }
    });
};

const bootCartForms = () => {
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-eclise-cart-form]');

        if (!form || form.dataset.pending === '1') {
            return;
        }

        event.preventDefault();

        if (form.dataset.confirmTitle) {
            const confirmation = await EcliseAlert.confirm({
                danger: form.dataset.cartAction === 'remove',
                title: form.dataset.confirmTitle,
                text: form.dataset.confirmText || '',
                confirmButtonText: form.dataset.confirmButtonText || 'Confirm',
            });

            if (!confirmation.isConfirmed) {
                return;
            }
        }

        form.dataset.pending = '1';
        setPending(form, true);

        try {
            const { response, json } = await requestJson(form);

            if (response.ok) {
                updateCartBadges(json?.cart?.count);
                updateCartPage(json?.cart);
                resetBulkCartForm(form);
                EcliseAlert.toast(json?.message || 'Cart updated.');
                return;
            }

            if (response.status === 422 && json?.errors) {
                EcliseAlert.warning(firstError(json.errors) || 'Please review the highlighted fields and try again.');
                return;
            }

            if (response.status === 401) {
                await handleAuthFailure(json);
                return;
            }

            if (response.status === 403) {
                await EcliseAlert.error('You are not allowed to complete this action.');
                return;
            }

            await EcliseAlert.error(json?.message || 'We could not complete your request at this time. Please try again.');
        } catch {
            await EcliseAlert.error('We could not connect to the server. Please check your connection and try again.');
        } finally {
            delete form.dataset.pending;
            setPending(form, false);
        }
    });
};

const bootReducedMotionCarousel = () => {
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    document.querySelectorAll('[data-eclise-home-carousel]').forEach((carousel) => {
        carousel.removeAttribute('data-bs-ride');
        carousel.removeAttribute('data-bs-interval');

        if (window.bootstrap?.Carousel) {
            window.bootstrap.Carousel.getOrCreateInstance(carousel, {
                interval: false,
                ride: false,
            }).pause();
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    bootFlashAlerts();
    bootContactForms();
    bootCartForms();
    bootReducedMotionCarousel();
});
