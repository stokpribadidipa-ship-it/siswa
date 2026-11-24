(() => {
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const mobileNavigation = window.matchMedia('(max-width: 1023.98px)');

    function setMenuOpen(open) {
        if (!sidebar || !overlay || !toggle) return;
        if (!mobileNavigation.matches) {
            sidebar.classList.remove('open');
            sidebar.inert = false;
            overlay.classList.remove('open');
            document.body.classList.remove('sidebar-open');
            const collapsed = document.body.classList.contains('sidebar-collapsed');
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('aria-label', collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar');
            const icon = toggle.querySelector('i');
            icon?.classList.remove('bi-list', 'bi-x-lg');
            icon?.classList.toggle('bi-layout-sidebar-inset', !collapsed);
            icon?.classList.toggle('bi-layout-sidebar', collapsed);
            return;
        }
        open = open && mobileNavigation.matches;
        sidebar.classList.toggle('open', open);
        sidebar.inert = mobileNavigation.matches && !open;
        overlay.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        const icon = toggle.querySelector('i');
        icon?.classList.remove('bi-layout-sidebar-inset', 'bi-layout-sidebar');
        icon?.classList.toggle('bi-x-lg', open);
        icon?.classList.toggle('bi-list', !open);
        document.body.classList.toggle('sidebar-open', open);
    }

    toggle?.addEventListener('click', () => {
        if (mobileNavigation.matches) {
            setMenuOpen(!sidebar.classList.contains('open'));
            return;
        }
        document.body.classList.toggle('sidebar-collapsed');
        setMenuOpen(false);
    });
    sidebarClose?.addEventListener('click', () => setMenuOpen(false));
    overlay?.addEventListener('click', () => setMenuOpen(false));
    sidebar?.querySelectorAll('.side-nav a').forEach((link) => {
        link.addEventListener('click', () => setMenuOpen(false));
    });
    setMenuOpen(false);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar?.classList.contains('open')) {
            setMenuOpen(false);
            toggle?.focus();
        }
    });
    mobileNavigation.addEventListener('change', () => {
        document.body.classList.remove('sidebar-collapsed');
        setMenuOpen(false);
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

    document.querySelectorAll('input[type="file"][name="foto"]').forEach((input) => {
        input.addEventListener('change', () => {
            const preview = input.closest('.form-wide')?.querySelector('.photo-preview');
            if (!preview) return;
            preview.replaceChildren();
            const file = input.files?.[0];
            if (!file) return;
            if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 2 * 1024 * 1024) return;
            const image = document.createElement('img');
            image.className = 'student-photo-preview';
            image.alt = 'Pratinjau foto siswa';
            image.src = URL.createObjectURL(file);
            preview.append(image);
        });
    });

    function updateRoleStudentField(form) {
        const roleSelect = form.querySelector('[data-role-select]');
        const studentSelect = form.querySelector('[data-student-select]');
        if (!roleSelect || !studentSelect) return;
        const isStudent = roleSelect.value === 'siswa';
        studentSelect.hidden = !isStudent;
        studentSelect.required = isStudent;
        studentSelect.setAttribute('aria-required', String(isStudent));
    }

    document.querySelectorAll('[data-role-form]').forEach(updateRoleStudentField);
    document.addEventListener('change', (event) => {
        const roleForm = event.target.closest('[data-role-form]');
        if (roleForm) updateRoleStudentField(roleForm);
    });

    let feedbackSequence = 0;
    function isFormField(field) {
        return field instanceof HTMLInputElement
            || field instanceof HTMLTextAreaElement
            || field instanceof HTMLSelectElement;
    }

    function syncRequiredMark(field) {
        field.labels?.forEach((label) => {
            let mark = label.querySelector('.required-mark');
            if (field.required && !mark) {
                mark = document.createElement('span');
                mark.className = 'required-mark';
                mark.setAttribute('aria-hidden', 'true');
                mark.textContent = '*';
                label.append(mark);
            } else if (!field.required && mark) {
                mark.remove();
            }
        });
    }

    function syncOptionalPasswordFields(form) {
        const fields = [...form.querySelectorAll('[data-optional-password]')];
        if (fields.length === 0) return;
        const passwordProvided = fields.some((field) => field.value !== '');
        fields.forEach((field) => {
            field.required = passwordProvided;
            syncRequiredMark(field);
        });
    }

    function fieldFeedback(field, create = true) {
        const control = field.closest('.input-group') || field;
        const searchControl = control.parentElement?.matches('.search-control') ? control.parentElement : null;
        const feedbackParent = searchControl?.parentElement || control.parentElement;
        if (!feedbackParent) return null;
        let feedback = field.dataset.feedbackId
            ? document.getElementById(field.dataset.feedbackId)
            : null;
        if (!feedback && create) {
            feedback = document.createElement('div');
            feedback.className = 'field-feedback';
            feedback.id = field.id ? `${field.id}-feedback` : `field-feedback-${++feedbackSequence}`;
            field.dataset.feedbackId = feedback.id;
            feedback.hidden = true;
            feedback.setAttribute('aria-live', 'polite');
            (searchControl || control).insertAdjacentElement('afterend', feedback);
        }
        return feedback || null;
    }

    function setDescribedBy(field, id, include) {
        const descriptions = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        const updated = include
            ? [...new Set([...descriptions, id])]
            : descriptions.filter((description) => description !== id);
        if (updated.length > 0) field.setAttribute('aria-describedby', updated.join(' '));
        else field.removeAttribute('aria-describedby');
    }

    function updateMatchValidity(field) {
        const targetId = field.dataset.match;
        if (!targetId) return;
        const target = document.getElementById(targetId);
        if (target && field.value !== '' && target.value !== '' && field.value !== target.value) {
            field.setCustomValidity(field.dataset.matchMessage || 'Konfirmasi password harus sama dengan password.');
        } else {
            field.setCustomValidity('');
        }
    }

    function fieldErrorMessage(field) {
        const validity = field.validity;
        const label = (field.labels?.[0]?.textContent || field.getAttribute('aria-label') || 'Isian')
            .replace(/\*/g, '').trim();
        if (validity.valueMissing) return `${label} wajib diisi.`;
        if (validity.typeMismatch && field.type === 'email') return 'Masukkan alamat email dengan format yang benar.';
        if (validity.tooShort) return `${label} minimal ${field.minLength} karakter.`;
        if (validity.tooLong) return `${label} maksimal ${field.maxLength} karakter.`;
        if (validity.patternMismatch && field.title) return field.title;
        if (validity.patternMismatch) return `Format ${label.toLowerCase()} belum sesuai.`;
        if (validity.customError) return field.validationMessage;
        return field.validationMessage || `Periksa kembali ${label.toLowerCase()}.`;
    }

    const serverErrorFields = [
        { terms: ['konfirmasi password'], names: ['password_confirmation', 'konfirmasi_password'] },
        { terms: ['password lama'], names: ['password_lama'] },
        { terms: ['password baru'], names: ['password_baru'] },
        { terms: ['nis'], names: ['nis'] },
        { terms: ['email'], names: ['email'] },
        { terms: ['nama'], names: ['nama'] },
        { terms: ['kelas'], names: ['kelas'] },
        { terms: ['jurusan'], names: ['jurusan'] },
        { terms: ['jenis kelamin'], names: ['jenis_kelamin'] },
        { terms: ['nomor hp', 'telepon'], names: ['no_telepon'] },
        { terms: ['alamat'], names: ['alamat'] },
        { terms: ['password'], names: ['password'] },
    ];

    function attachServerErrors(form, messages) {
        const remaining = [];
        let firstField = null;
        messages.forEach((message) => {
            const normalizedMessage = message.toLocaleLowerCase('id');
            const rules = serverErrorFields.filter((rule) =>
                rule.terms.some((term) => normalizedMessage.includes(term))
            );
            const fields = [...form.elements].filter((field) =>
                isFormField(field) && rules.some((rule) => rule.names.includes(field.name))
            );
            if (fields.length === 0) {
                remaining.push(message);
                return;
            }
            fields.forEach((field) => {
                const feedback = fieldFeedback(field);
                field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');
                feedback.textContent = message;
                feedback.hidden = false;
                setDescribedBy(field, feedback.id, true);
                if (!firstField) firstField = field;
            });
        });
        return { remaining, firstField };
    }

    function validateField(field, force = false) {
        if (!isFormField(field) || !field.willValidate) return true;
        syncOptionalPasswordFields(field.form);
        updateMatchValidity(field);
        const valid = field.checkValidity();
        if (!force && field.dataset.touched !== 'true') return valid;

        const feedback = fieldFeedback(field);
        if (valid) {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
            if (feedback) {
                feedback.hidden = true;
                feedback.textContent = '';
                setDescribedBy(field, feedback.id, false);
            }
            return true;
        }

        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        if (feedback) {
            feedback.textContent = fieldErrorMessage(field);
            feedback.hidden = false;
            setDescribedBy(field, feedback.id, true);
        }
        return false;
    }

    function clearFieldState(field) {
        if (!isFormField(field)) return;
        field.classList.remove('is-invalid', 'is-valid');
        field.removeAttribute('aria-invalid');
        delete field.dataset.touched;
        const feedback = fieldFeedback(field, false);
        if (feedback) {
            setDescribedBy(field, feedback.id, false);
            feedback.remove();
        }
        delete field.dataset.feedbackId;
    }

    function setButtonLoading(button, label) {
        if (!button) return () => {};
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm me-2';
        spinner.setAttribute('aria-hidden', 'true');
        button.replaceChildren(spinner, document.createTextNode(label));
        return () => {
            button.innerHTML = originalHtml;
            button.disabled = false;
            button.removeAttribute('aria-busy');
        };
    }

    document.querySelectorAll('.form-label').forEach((label) => {
        const field = label.control || document.getElementById(label.htmlFor);
        if (field) syncRequiredMark(field);
    });
    document.addEventListener('input', (event) => {
        const field = event.target;
        if (!isFormField(field)) return;
        syncOptionalPasswordFields(field.form);
        field.dataset.touched = 'true';
        validateField(field);
        document.querySelectorAll('[data-match]').forEach((matchingField) => {
            if (matchingField.dataset.match === field.id && matchingField.dataset.touched === 'true') {
                validateField(matchingField);
            }
        });
    });
    document.addEventListener('change', (event) => {
        const field = event.target;
        if (!isFormField(field)) return;
        syncOptionalPasswordFields(field.form);
        field.dataset.touched = 'true';
        validateField(field);
    });
    document.addEventListener('blur', (event) => {
        const field = event.target;
        if (!isFormField(field)) return;
        syncOptionalPasswordFields(field.form);
        field.dataset.touched = 'true';
        validateField(field);
    }, true);

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

    document.querySelectorAll('form').forEach((form) => {
        form.noValidate = true;
    });
    document.querySelectorAll('.alert-danger').forEach((alert) => {
        const scope = alert.closest('.panel, .auth-card');
        const form = scope?.querySelector('form');
        if (!form) return;
        const messages = [...alert.querySelectorAll('li')]
            .map((item) => item.textContent.trim())
            .filter(Boolean);
        if (messages.length === 0 && alert.textContent.trim()) messages.push(alert.textContent.trim());
        attachServerErrors(form, messages);
    });
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        syncOptionalPasswordFields(form);
        const invalidFields = [...form.elements].filter((field) => isFormField(field) && !validateField(field, true));
        if (invalidFields.length === 0) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        invalidFields[0].focus();
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
    confirmationDialog.querySelector('[data-confirm-delete]').addEventListener('click', async () => {
        if (!pendingForm) return;
        const form = pendingForm;
        pendingForm = null;
        confirmationDialog.close();
        if (form.matches('[data-student-delete-form]')) {
            await deleteStudent(form);
            return;
        }
        if (form.matches('[data-user-delete-form]')) {
            await deleteUser(form);
            return;
        }
        form.dataset.confirmed = 'true';
        form.requestSubmit();
    });
    confirmationDialog.addEventListener('click', (event) => {
        if (event.target === confirmationDialog) {
            pendingForm = null;
            confirmationDialog.close();
        }
    });

    const studentDialog = document.querySelector('[data-student-dialog]');
    const studentForm = studentDialog?.querySelector('[data-student-ajax-form]');
    const studentTable = document.querySelector('[data-student-table]');
    const studentTableContainer = studentTable?.closest('.table-responsive');
    const filterForm = document.querySelector('[data-student-filter]');
    const countLabel = document.querySelector('[data-student-count]');
    const paginationContainer = document.querySelector('[data-student-pagination]');
    const userFilterForm = document.querySelector('[data-user-filter]');
    const userTable = document.querySelector('[data-user-table]');
    const userCountLabel = document.querySelector('[data-user-count]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let activeListRequest = null;
    let activeUserListRequest = null;
    let searchTimeout = null;
    let userSearchTimeout = null;
    let studentFormBusy = false;
    const busyUserForms = new WeakSet();

    function showNotice(type, message) {
        const mainContent = document.querySelector('.main-content');
        if (!mainContent) return;

        const notice = document.createElement('div');
        notice.className = `alert alert-${type} alert-dismissible fade show`;
        notice.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        notice.append(document.createTextNode(message));

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.setAttribute('data-bs-dismiss', 'alert');
        closeButton.setAttribute('aria-label', 'Tutup');
        notice.append(closeButton);
        mainContent.prepend(notice);
    }

    async function readJson(response, fallbackMessage) {
        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error(fallbackMessage);
        }

        if (!response.ok || !payload.success) {
            const error = new Error(payload.message || fallbackMessage);
            error.errors = Array.isArray(payload.errors) ? payload.errors : [];
            throw error;
        }
        return payload;
    }

    async function refreshStudentTable(page = '1', updateAddress = true) {
        if (!studentTable || !filterForm) return false;

        // Batalkan hasil lama agar respons terlambat tidak menimpa pencarian terbaru.
        activeListRequest?.abort();
        const controller = new AbortController();
        activeListRequest = controller;
        filterForm.setAttribute('aria-busy', 'true');
        studentTableContainer?.setAttribute('aria-busy', 'true');

        const query = new URLSearchParams(new FormData(filterForm));
        query.set('page', String(page));
        const addressQuery = new URLSearchParams(query);
        const requestQuery = new URLSearchParams(query);
        requestQuery.set('ajax', '1');

        try {
            const response = await fetch(`${window.location.pathname}?${requestQuery.toString()}`, {
                headers: { 'X-Requested-With': 'fetch' },
                signal: controller.signal,
            });
            const payload = await readJson(response, 'Gagal memuat data siswa.');
            const data = payload.data;
            if (!data || typeof data.rowsHtml !== 'string' || typeof data.paginationHtml !== 'string') {
                throw new Error('Respons daftar siswa tidak lengkap.');
            }

            studentTable.innerHTML = data.rowsHtml;
            if (countLabel) countLabel.textContent = `${data.total} data sesuai pencarian dan filter.`;
            if (paginationContainer) paginationContainer.innerHTML = data.paginationHtml;
            addressQuery.set('page', String(data.page));
            if (updateAddress) {
                window.history.replaceState(null, '', `${window.location.pathname}?${addressQuery.toString()}`);
            }
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                showNotice('danger', error.message || 'Gagal memuat data siswa.');
            }
            return false;
        } finally {
            if (activeListRequest === controller) {
                activeListRequest = null;
                filterForm.removeAttribute('aria-busy');
                studentTableContainer?.removeAttribute('aria-busy');
            }
        }
    }

    function currentStudentPage() {
        return new URLSearchParams(window.location.search).get('page') || '1';
    }

    async function deleteStudent(form) {
        const submitButton = form.querySelector('button[type="submit"]');
        const restoreButton = setButtonLoading(submitButton, 'Menghapus...');

        const formData = new FormData(form);
        formData.set('action', 'delete');
        if (!formData.has('csrf_token')) formData.set('csrf_token', csrfToken);

        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: formData,
            });
            const payload = await readJson(response, 'Data siswa gagal dihapus.');
            await refreshStudentTable(currentStudentPage());
            showNotice('success', payload.message);
        } catch (error) {
            showNotice('danger', error.message || 'Data siswa gagal dihapus.');
        } finally {
            restoreButton();
        }
    }

    async function refreshUserTable() {
        if (!userTable || !userFilterForm) return false;

        activeUserListRequest?.abort();
        const controller = new AbortController();
        activeUserListRequest = controller;
        userFilterForm.setAttribute('aria-busy', 'true');

        const addressQuery = new URLSearchParams(new FormData(userFilterForm));
        const requestQuery = new URLSearchParams(addressQuery);
        requestQuery.set('ajax', '1');
        try {
            const response = await fetch(`${window.location.pathname}?${requestQuery.toString()}`, {
                headers: { 'X-Requested-With': 'fetch' },
                signal: controller.signal,
            });
            const payload = await readJson(response, 'Gagal memuat data pengguna.');
            if (!payload.data || typeof payload.data.rowsHtml !== 'string') {
                throw new Error('Respons daftar pengguna tidak lengkap.');
            }

            userTable.innerHTML = payload.data.rowsHtml;
            if (userCountLabel) userCountLabel.textContent = `${payload.data.total} pengguna ditemukan.`;
            window.history.replaceState(null, '', `${window.location.pathname}?${addressQuery.toString()}`);
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') {
                showNotice('danger', error.message || 'Gagal memuat data pengguna.');
            }
            return false;
        } finally {
            if (activeUserListRequest === controller) {
                activeUserListRequest = null;
                userFilterForm.removeAttribute('aria-busy');
            }
        }
    }

    async function submitUserForm(form) {
        if (busyUserForms.has(form)) return;
        const submitButton = form.querySelector('button[type="submit"]');
        const restoreButton = setButtonLoading(submitButton, 'Menyimpan...');
        busyUserForms.add(form);
        form.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: new FormData(form),
            });
            const payload = await readJson(response, 'Perubahan pengguna gagal disimpan.');
            if (form.matches('.create-user-form')) {
                form.reset();
                [...form.elements].forEach(clearFieldState);
            }
            showNotice('success', payload.message);
            await refreshUserTable();
        } catch (error) {
            if (error.errors?.length) {
                const { remaining, firstField } = attachServerErrors(form, error.errors);
                if (remaining.length > 0) showValidationDialog(remaining, firstField);
                else showNotice('danger', error.message || 'Periksa kembali data yang dimasukkan.');
            } else {
                showNotice('danger', error.message || 'Perubahan pengguna gagal disimpan.');
            }
        } finally {
            busyUserForms.delete(form);
            form.removeAttribute('aria-busy');
            restoreButton();
        }
    }

    async function deleteUser(form) {
        const submitButton = form.querySelector('button[type="submit"]');
        const restoreButton = setButtonLoading(submitButton, 'Menghapus...');

        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: new FormData(form),
            });
            const payload = await readJson(response, 'Pengguna gagal dihapus.');
            await refreshUserTable();
            showNotice('success', payload.message);
        } catch (error) {
            showNotice('danger', error.message || 'Pengguna gagal dihapus.');
        } finally {
            restoreButton();
        }
    }

    function populateStudentForm(student = {}) {
        if (!studentForm) return;
        const actionInput = studentForm.querySelector('input[name="action"]');
        const idInput = studentForm.querySelector('input[name="id"]');
        const title = studentForm.closest('[data-student-dialog]')?.querySelector('#studentDialogTitle');
        const submitButton = studentForm.querySelector('button[type="submit"]');

        if (actionInput) actionInput.value = student.id ? 'update' : 'create';
        if (idInput) idInput.value = student.id || '';
        if (title) title.textContent = student.id ? 'Perbarui data siswa' : 'Tambah siswa';
        if (submitButton) submitButton.textContent = student.id ? 'Simpan perubahan' : 'Simpan siswa';
        [...studentForm.elements].forEach(clearFieldState);

        ['nis', 'nama', 'kelas_id', 'jurusan_id', 'jenis_kelamin', 'no_telepon', 'alamat'].forEach((fieldName) => {
            const field = studentForm.querySelector(`[name="${fieldName}"]`);
            if (!field) return;
            field.value = student[fieldName] ?? '';
            field.setCustomValidity('');
        });
    }

    document.addEventListener('click', async (event) => {
        const createButton = event.target.closest('[data-student-create]');
        if (createButton) {
            event.preventDefault();
            populateStudentForm();
            studentDialog?.showModal();
            return;
        }

        const editButton = event.target.closest('[data-student-edit]');
        if (editButton) {
            event.preventDefault();
            const id = editButton.getAttribute('data-student-id');
            if (!id) return;

            try {
                const response = await fetch(`${window.location.pathname}?ajax=student&id=${encodeURIComponent(id)}`, {
                    headers: { 'X-Requested-With': 'fetch' },
                });
                const payload = await readJson(response, 'Data siswa tidak dapat dimuat.');
                populateStudentForm(payload.data);
                studentDialog?.showModal();
            } catch (error) {
                showNotice('danger', error.message || 'Data siswa tidak dapat dimuat.');
            }
        }

        const pageLink = event.target.closest('[data-student-pagination] a');
        if (pageLink) {
            event.preventDefault();
            const page = new URL(pageLink.href).searchParams.get('page') || '1';
            await refreshStudentTable(page);
        }

        if (event.target.closest('[data-student-dialog-close]')) {
            studentDialog?.close();
        }
    });

    document.addEventListener('input', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) return;
        if (field.matches('[data-student-search]')) {
            clearTimeout(searchTimeout);
            searchTimeout = window.setTimeout(() => void refreshStudentTable('1'), 350);
        }
    });

    filterForm?.addEventListener('keyup', (event) => {
        if (!event.target.matches('[data-student-search]')) return;
        clearTimeout(searchTimeout);
        searchTimeout = window.setTimeout(() => void refreshStudentTable('1'), 350);
    });
    filterForm?.addEventListener('change', (event) => {
        if (event.target.matches('[data-student-search]')) return;
        clearTimeout(searchTimeout);
        void refreshStudentTable('1');
    });
    filterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(searchTimeout);
        void refreshStudentTable('1');
    });
    userFilterForm?.addEventListener('input', (event) => {
        if (!event.target.matches('[data-user-search]')) return;
        clearTimeout(userSearchTimeout);
        userSearchTimeout = window.setTimeout(() => void refreshUserTable(), 350);
    });
    userFilterForm?.addEventListener('keyup', (event) => {
        if (!event.target.matches('[data-user-search]')) return;
        clearTimeout(userSearchTimeout);
        userSearchTimeout = window.setTimeout(() => void refreshUserTable(), 350);
    });
    userFilterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(userSearchTimeout);
        void refreshUserTable();
    });

    window.addEventListener('popstate', () => {
        if (!filterForm) return;
        const query = new URLSearchParams(window.location.search);
        const defaults = { q: '', kelas: '', jurusan: '', sort: 'nama', direction: 'asc' };
        filterForm.querySelectorAll('[name]').forEach((field) => {
            field.value = query.has(field.name) ? query.get(field.name) : (defaults[field.name] || '');
        });
        void refreshStudentTable(query.get('page') || '1', false);
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (form instanceof HTMLFormElement && form.matches('[data-user-ajax-form]')) {
            event.preventDefault();
            if (!form.checkValidity()) return;
            await submitUserForm(form);
            return;
        }
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-student-ajax-form]')) return;
        event.preventDefault();
        if (studentFormBusy || !form.checkValidity()) return;

        const submitButton = form.querySelector('button[type="submit"]');
        const restoreButton = setButtonLoading(submitButton, 'Menyimpan...');
        studentFormBusy = true;
        form.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                body: new FormData(form),
            });
            const payload = await readJson(response, 'Data siswa gagal disimpan.');
            studentDialog?.close();
            showNotice('success', payload.message);
            await refreshStudentTable(currentStudentPage());
        } catch (error) {
            if (error.errors?.length) {
                const { remaining, firstField } = attachServerErrors(form, error.errors);
                if (remaining.length > 0) showValidationDialog(remaining, firstField);
                else showNotice('danger', error.message || 'Periksa kembali data yang dimasukkan.');
            } else {
                showNotice('danger', error.message || 'Data siswa gagal disimpan.');
            }
        } finally {
            studentFormBusy = false;
            form.removeAttribute('aria-busy');
            restoreButton();
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (form.matches('[data-user-ajax-form], [data-student-ajax-form], [data-student-filter], [data-user-filter]')) return;
        const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton) setButtonLoading(submitButton, 'Memproses...');
    });
})();
