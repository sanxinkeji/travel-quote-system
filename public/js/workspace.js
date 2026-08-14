(function () {
    'use strict';

    const toast = document.querySelector('[data-toast]');
    window.showWorkspaceToast = function (message, type) {
        if (!toast) return;
        toast.textContent = message;
        toast.className = `toast show ${type || ''}`;
        window.clearTimeout(window.workspaceToastTimer);
        window.workspaceToastTimer = window.setTimeout(() => toast.classList.remove('show'), 2600);
    };

    const routeProgress = document.querySelector('[data-route-progress]');
    let navigationStarted = false;

    function beginRouteProgress(trigger) {
        if (!routeProgress || navigationStarted) return;
        navigationStarted = true;
        document.body.classList.add('is-navigating');
        routeProgress.setAttribute('aria-hidden', 'false');
        if (trigger) {
            trigger.classList.add('is-pending');
            trigger.setAttribute('aria-busy', 'true');
        }
    }

    function clearRouteProgress() {
        navigationStarted = false;
        document.body.classList.remove('is-navigating');
        routeProgress?.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('.is-pending').forEach((item) => {
            item.classList.remove('is-pending');
            item.removeAttribute('aria-busy');
        });
    }

    window.beginRouteProgress = beginRouteProgress;
    window.addEventListener('pageshow', clearRouteProgress);

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || link.target === '_blank' || link.dataset.noRouteProgress !== undefined) return;

        const url = new URL(link.href, window.location.href);
        if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

        beginRouteProgress(link);
    });

    document.addEventListener('submit', (event) => {
        window.setTimeout(() => {
            const form = event.target;
            if (event.defaultPrevented || form.target === '_blank' || form.dataset.noRouteProgress !== undefined) return;
            beginRouteProgress(event.submitter || form.querySelector('[type="submit"]'));
        }, 0);
    });

    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');
        if (!trigger || !menu) return;
        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const opening = menu.hidden;
            document.querySelectorAll('[data-dropdown-menu]').forEach((item) => { item.hidden = true; });
            menu.hidden = !opening;
            trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => { menu.hidden = true; });
        document.querySelectorAll('[data-dropdown-trigger]').forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.querySelector(button.dataset.passwordToggle);
            if (!input) return;
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-label', reveal ? '隐藏密码' : '显示密码');
            button.title = reveal ? '隐藏密码' : '显示密码';
        });
    });

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.querySelector(`[data-modal="${button.dataset.openModal}"]`);
            if (!modal) return;
            modal.hidden = false;
            document.body.classList.add('modal-open');
            modal.querySelector('input')?.focus();
        });
    });
    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            const backdrop = button.closest('[data-modal]');
            if (backdrop) backdrop.hidden = true;
            document.body.classList.remove('modal-open');
        });
    });
    document.querySelectorAll('[data-modal]').forEach((backdrop) => {
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) {
                backdrop.hidden = true;
                document.body.classList.remove('modal-open');
            }
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
})();
