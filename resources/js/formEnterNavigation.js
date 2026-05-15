const FIELD_SELECTOR = [
    'input:not([type="hidden"]):not([type="button"]):not([type="submit"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):not([type="range"]):not([type="color"])',
    'select',
    'textarea',
    '[contenteditable="true"]',
].join(',');

const isDisabledField = (element) => (
    element.disabled
    || element.readOnly
    || element.getAttribute('aria-disabled') === 'true'
);

const isVisibleField = (element) => {
    if (!element.isConnected) {
        return false;
    }

    const style = window.getComputedStyle(element);

    return style.display !== 'none'
        && style.visibility !== 'hidden'
        && element.getClientRects().length > 0;
};

const canUseEnterNavigation = (event) => (
    event.key === 'Enter'
    && !event.isComposing
    && !event.altKey
    && !event.ctrlKey
    && !event.metaKey
);

const getFieldScope = (field) => (
    field.closest('form')
    ?? field.closest('[data-enter-next-scope]')
    ?? document
);

const getNavigableFields = (scope) => Array.from(scope.querySelectorAll(FIELD_SELECTOR))
    .filter((field) => !isDisabledField(field) && isVisibleField(field) && field.closest('[data-enter-next="false"]') === null);

const focusField = (field) => {
    field.focus();
};

const submitForm = (form) => {
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
    }

    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
};

const handleEnterNavigation = (event) => {
    if (!canUseEnterNavigation(event)) {
        return;
    }

    const field = event.target;

    if (!(field instanceof HTMLElement) || !field.matches(FIELD_SELECTOR)) {
        return;
    }

    if (field.closest('[data-enter-next="false"]')) {
        return;
    }

    if (field instanceof HTMLTextAreaElement && event.shiftKey) {
        return;
    }

    const scope = getFieldScope(field);
    const fields = getNavigableFields(scope);
    const currentIndex = fields.indexOf(field);

    if (currentIndex === -1) {
        return;
    }

    const nextField = fields[currentIndex + 1];

    if (nextField) {
        event.preventDefault();
        focusField(nextField);
        return;
    }

    const form = field.closest('form');

    if (form) {
        event.preventDefault();
        submitForm(form);
    }
};

export const initializeFormEnterNavigation = () => {
    if (typeof document === 'undefined') {
        return;
    }

    window.__formEnterNavigationCleanup?.();
    document.addEventListener('keydown', handleEnterNavigation);

    window.__formEnterNavigationCleanup = () => {
        document.removeEventListener('keydown', handleEnterNavigation);
    };
};
