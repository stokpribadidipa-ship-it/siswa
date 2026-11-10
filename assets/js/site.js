(() => {
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('menuToggle');

    function setMenuOpen(open) {
        if (!sidebar || !overlay || !toggle) return;
        sidebar.classList.toggle('open', open);
        overlay.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', String(open));
    }

    toggle?.addEventListener('click', () => setMenuOpen(!sidebar.classList.contains('open')));
    overlay?.addEventListener('click', () => setMenuOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setMenuOpen(false);
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-label', visible ? 'Sembunyikan password' : 'Tampilkan password');
            button.innerHTML = `<i class="bi bi-eye${visible ? '-slash' : ''}"></i>`;
        });
    });

    document.querySelectorAll('[data-role-form]').forEach((form) => {
        const roleSelect = form.querySelector('[data-role-select]');
        const studentSelect = form.querySelector('[data-student-select]');
        if (!roleSelect || !studentSelect) return;

        const updateStudentField = () => {
            const isStudent = roleSelect.value === 'siswa';
            studentSelect.hidden = !isStudent;
            studentSelect.required = isStudent;
        };

        roleSelect.addEventListener('change', updateStudentField);
        updateStudentField();
    });

    const validationDialog = document.createElement('dialog');
    validationDialog.className = 'validation-dialog';
    validationDialog.setAttribute('aria-labelledby', 'validationDialogTitle');
    validationDialog.setAttribute('aria-describedby', 'validationDialogDescription');
    validationDialog.innerHTML = `
        <div class="validation-dialog-icon" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></div>
        <h2 id="validationDialogTitle">Periksa kembali input Anda</h2>
        <p id="validationDialogDescription">Ada bagian yang perlu diperbaiki sebelum data dapat disimpan.</p>
        <ul class="validation-dialog-errors"></ul>
        <div class="validation-dialog-actions">
            <button type="button" class="btn btn-primary" data-validation-close>Perbaiki input</button>
        </div>
    `;
    document.body.appendChild(validationDialog);
    const errorContainer = validationDialog.querySelector('.validation-dialog-errors');
    let fieldToFocus = null;
    const showValidationDialog = (messages, focusTarget = null) => {
        errorContainer.replaceChildren(...messages.map((message) => {
            const item = document.createElement('li');
            item.textContent = message;
            return item;
        }));
        fieldToFocus = focusTarget;
        if (!validationDialog.open) validationDialog.showModal();
    };

    const serverMessages = [];
    document.querySelectorAll('.alert-danger').forEach((alert) => {
        const items = [...alert.querySelectorAll('li')].map((item) => item.textContent.trim()).filter(Boolean);
        const message = alert.cloneNode(true);
        message.querySelectorAll('.btn-close, [data-bs-dismiss="alert"]').forEach((button) => button.remove());
        const fallbackMessage = message.textContent.trim();
        if (items.length > 0) {
            serverMessages.push(...items);
        } else if (fallbackMessage !== '') {
            serverMessages.push(fallbackMessage);
        }
        alert.hidden = true;
    });
    if (serverMessages.length > 0) showValidationDialog(serverMessages);

    document.querySelectorAll('form').forEach((form) => {
        form.noValidate = true;
    });
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.checkValidity()) return;

        event.preventDefault();
        const invalidFields = [...form.querySelectorAll(':invalid')];
        const messages = invalidFields.map((field) => {
            const label = field.labels?.[0]?.textContent.trim() || field.getAttribute('aria-label') || 'Isian';
            return `${label}: ${field.validationMessage}`;
        });
        showValidationDialog(messages, invalidFields[0] ?? null);
    });

    validationDialog.querySelector('[data-validation-close]').addEventListener('click', () => {
        validationDialog.close();
    });
    validationDialog.addEventListener('close', () => {
        fieldToFocus?.focus();
        fieldToFocus = null;
    });
    validationDialog.addEventListener('click', (event) => {
        if (event.target === validationDialog) validationDialog.close();
    });

    let pendingForm = null;
    const confirmationDialog = document.createElement('dialog');
    confirmationDialog.className = 'confirm-dialog';
    confirmationDialog.setAttribute('aria-labelledby', 'confirmDialogTitle');
    confirmationDialog.innerHTML = `
        <div class="confirm-dialog-icon" aria-hidden="true"><i class="bi bi-trash3"></i></div>
        <h2 id="confirmDialogTitle">Konfirmasi penghapusan</h2>
        <p class="confirm-dialog-message"></p>
        <div class="confirm-dialog-actions">
            <button type="button" class="btn btn-light" data-confirm-cancel>Batal</button>
            <button type="button" class="btn btn-danger" data-confirm-delete>Ya, hapus</button>
        </div>
    `;
    document.body.appendChild(confirmationDialog);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('form[data-confirm]')) return;
        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }

        event.preventDefault();
        pendingForm = form;
        confirmationDialog.querySelector('.confirm-dialog-message').textContent = form.dataset.confirm;
        confirmationDialog.showModal();
    });

    confirmationDialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => {
        pendingForm = null;
        confirmationDialog.close();
    });
    confirmationDialog.querySelector('[data-confirm-delete]').addEventListener('click', () => {
        if (!pendingForm) return;
        const form = pendingForm;
        pendingForm = null;
        form.dataset.confirmed = 'true';
        confirmationDialog.close();
        form.requestSubmit();
    });
    confirmationDialog.addEventListener('click', (event) => {
        if (event.target === confirmationDialog) {
            pendingForm = null;
            confirmationDialog.close();
        }
    });
})();
