// DHRU Server panel behaviour. No dependencies.
(function () {
    'use strict';

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

    // ----- Light / dark theme (dark is the default; the choice is remembered in this browser) -----
    $$('[data-theme-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var root = document.documentElement;
            var light = root.getAttribute('data-theme') !== 'light';
            if (light) { root.setAttribute('data-theme', 'light'); } else { root.removeAttribute('data-theme'); }
            try { localStorage.setItem('theme', light ? 'light' : 'dark'); } catch (e) { /* storage blocked */ }
        });
    });

    // ----- Buttons that show / hide a panel, e.g. the Search box on the orders page -----
    $$('[data-toggle-target]').forEach(function (btn) {
        var panel = $(btn.getAttribute('data-toggle-target'));
        if (!panel) { return; }
        btn.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            btn.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            if (!panel.hidden) { var first = $('input, select', panel); if (first) { first.focus(); } }
        });
    });

    // ----- Menu button -----
    // Phones and tablets: the menu slides in over the page. Desktop: the sidebar collapses to give the page full width,
    // and the choice is remembered in this browser. Pages without a sidebar (sign-in) always use the slide-in drawer.
    var narrow = window.matchMedia('(max-width: 960px)');
    var hasSidebar = !!$('#sidebar');
    var syncMenuButtons = function () {
        var open = (narrow.matches || !hasSidebar)
            ? document.body.classList.contains('nav-open')
            : !document.body.classList.contains('nav-collapsed');
        $$('.menu-btn[data-nav-toggle]').forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });
    };
    $$('[data-nav-toggle]').forEach(function (el) {
        el.addEventListener('click', function () {
            if (narrow.matches || !hasSidebar) {
                document.body.classList.toggle('nav-open');
            } else {
                var collapsed = document.body.classList.toggle('nav-collapsed');
                try { localStorage.setItem('nav', collapsed ? 'collapsed' : 'open'); } catch (e) { /* storage blocked */ }
            }
            syncMenuButtons();
        });
    });
    // Leaving phone width with the slide-in open would leave the dark overlay behind.
    var onWidthChange = function () { if (!narrow.matches && hasSidebar) { document.body.classList.remove('nav-open'); } syncMenuButtons(); };
    if (narrow.addEventListener) { narrow.addEventListener('change', onWidthChange); } else if (narrow.addListener) { narrow.addListener(onWidthChange); }
    syncMenuButtons();

    // ----- Home page: notice-board popup, once per visit -----
    var popup = $('[data-home-popup]');
    if (popup) {
        var seen = false;
        try { seen = sessionStorage.getItem('home-popup') === 'seen'; } catch (e) { /* storage blocked: show it */ }
        var closePopup = function () {
            popup.hidden = true;
            document.body.classList.remove('modal-open');
            try { sessionStorage.setItem('home-popup', 'seen'); } catch (e) { /* storage blocked */ }
        };
        if (!seen) {
            popup.hidden = false;
            document.body.classList.add('modal-open');
            var closeBtn = $('[data-popup-close]', popup);
            if (closeBtn) { closeBtn.focus({ preventScroll: true }); }
        }
        popup.addEventListener('click', function (e) {
            if (e.target === popup || e.target.closest('[data-popup-close]')) { closePopup(); }
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !popup.hidden) { closePopup(); } });
    }

    // ----- Offer countdown (hh:mm:ss, or "2d 05:10:33" when more than a day is left) -----
    $$('[data-countdown]').forEach(function (box) {
        var end = new Date(box.getAttribute('data-countdown')).getTime();
        var out = $('[data-countdown-text]', box);
        if (!out || isNaN(end)) { return; }
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var tick = function () {
            var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
            var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
            out.textContent = (d > 0 ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
            if (left === 0) { clearInterval(timer); box.closest('.home-offer').hidden = true; }
        };
        var timer = setInterval(tick, 1000);
        tick();
    });

    // ----- Server Tools: one command at a time, with a "Running…" label while it works -----
    $$('form[data-tool]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) { return; }
            form.classList.add('busy');
            $$('form[data-tool] button[type=submit]').forEach(function (b) { b.disabled = true; });
        });
    });

    // ----- Admin notification bell -----
    // Checks for news every minute; opening the bell reloads the list and marks everything as seen.
    var bell = $('[data-notifications]');
    if (bell) {
        var countEl = $('[data-notif-count]', bell);
        var listEl = $('[data-notif-list]', bell);
        var token = ($('meta[name=csrf-token]') || {}).content;
        var showCount = function (n) {
            countEl.hidden = !(n > 0);
            countEl.textContent = n >= 99 ? '99+' : String(n);
            document.title = document.title.replace(/^\(\d+\+?\) /, '') ;
            if (n > 0) { document.title = '(' + countEl.textContent + ') ' + document.title; }
        };
        var load = function () {
            return fetch(bell.getAttribute('data-url'), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (res) { if (!res.ok) { throw new Error(res.status); } return res.json(); })
                .then(function (data) {
                    // Do not swap the list under the admin's cursor while it is open.
                    if (!bell.open) { listEl.innerHTML = data.html; showCount(data.unread); }
                    return data;
                })
                .catch(function () { /* offline or signed out: try again next minute */ });
        };
        var markRead = function () {
            fetch(bell.getAttribute('data-read-url'), {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
            }).then(function () { showCount(0); }).catch(function () { /* ignore */ });
        };
        bell.addEventListener('toggle', function () {
            if (!bell.open) {
                // Closing: the highlighted "new" items are now old.
                $$('.notif-item.unread', listEl).forEach(function (a) { a.classList.remove('unread'); });
                return;
            }
            if (!countEl.hidden) { markRead(); }
        });
        showCount(parseInt(countEl.textContent, 10) || 0);
        setInterval(function () { if (!document.hidden) { load(); } }, 60000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { load(); } });
    }

    // ----- User dropdown: close on outside click or Escape -----
    document.addEventListener('click', function (e) {
        $$('details.user-menu[open], details.notif-menu[open]').forEach(function (d) { if (!d.contains(e.target)) { d.removeAttribute('open'); } });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        $$('details.user-menu[open], details.notif-menu[open]').forEach(function (d) { d.removeAttribute('open'); });
        document.body.classList.remove('nav-open');
    });

    // ----- "How it works" boxes remember whether they were hidden -----
    $$('details[data-guide]').forEach(function (guide) {
        var key = 'guide:' + guide.getAttribute('data-guide');
        try { if (localStorage.getItem(key) === 'closed') { guide.removeAttribute('open'); } } catch (e) { /* storage blocked */ }
        guide.addEventListener('toggle', function () {
            try { localStorage.setItem(key, guide.open ? 'open' : 'closed'); } catch (e) { /* storage blocked */ }
        });
    });

    // ----- Confirm before destructive or money-moving submits -----
    $$('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });

    // ----- Stop double submits (the server is idempotent too; this is just nicer) -----
    $$('form[data-once]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) { return; }
            $$('button[type=submit]', form).forEach(function (b) { b.disabled = true; });
        });
    });

    // ----- Selects that submit their filter form on change -----
    $$('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });

    // ----- Category form: add / remove input-field rows -----
    var rows = $('#field-rows');
    var template = $('#field-row-template');
    if (rows && template) {
        var next = rows.children.length;
        var add = function () {
            var html = template.innerHTML.replace(/__i__/g, String(next++));
            rows.insertAdjacentHTML('beforeend', html);
            var input = rows.lastElementChild.querySelector('input[type=text]');
            if (input) { input.focus(); }
        };
        $$('[data-add-field]').forEach(function (b) { b.addEventListener('click', add); });
        rows.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-remove-field]');
            if (btn) { btn.closest('.field-row').remove(); }
        });
    }

    // ----- Service form: show only the fields of the chosen category -----
    var categorySelect = $('#service-category');
    if (categorySelect) {
        var syncFields = function () {
            var shown = 0;
            $$('[data-category-fields]').forEach(function (group) {
                var match = group.getAttribute('data-category-fields') === categorySelect.value;
                group.hidden = !match;
                $$('input', group).forEach(function (i) { i.disabled = !match; });
                if (match && $$('input', group).length) { shown++; }
            });
            var none = $('#no-fields');
            if (none) { none.hidden = shown > 0 || !categorySelect.value; }
        };
        categorySelect.addEventListener('change', syncFields);
        syncFields();
    }

    // ----- Credit form: label and hint follow the operation -----
    var op = $('#credit-operation');
    if (op) {
        var syncOp = function () {
            var adj = op.value === 'adjustment';
            $$('[data-op]').forEach(function (el) { el.hidden = el.getAttribute('data-op') !== op.value; });
            var note = $('#credit-note');
            if (note) { note.required = adj; }
            var amount = $('#credit-amount');
            if (amount) { if (adj) { amount.removeAttribute('min'); } else { amount.setAttribute('min', '0.01'); } }
        };
        op.addEventListener('change', syncOp);
        syncOp();
    }

    // ----- Request status form: show the result or reason box that applies -----
    var statusSelect = $('#request-status');
    if (statusSelect) {
        var syncStatus = function () {
            $$('[data-for-status]').forEach(function (el) {
                el.hidden = el.getAttribute('data-for-status').split(' ').indexOf(statusSelect.value) === -1;
            });
        };
        statusSelect.addEventListener('change', syncStatus);
        syncStatus();
    }

    // ----- Description editor: a visual editor on top of <textarea data-editor> -----
    // The textarea stays the form field; the server cleans the HTML again before saving.
    $$('textarea[data-editor]').forEach(function (textarea) {
        var words = {};
        try { words = JSON.parse(textarea.getAttribute('data-text') || '{}'); } catch (e) { /* keep English */ }
        var t = function (s) { return words[s] || s; };
        var uploadUrl = textarea.getAttribute('data-upload-url');
        var csrf = ($('meta[name=csrf-token]') || {}).content;

        var wrap = document.createElement('div');
        wrap.className = 'editor';
        var bar = document.createElement('div');
        bar.className = 'editor-bar';
        bar.setAttribute('role', 'toolbar');
        var area = document.createElement('div');
        area.className = 'editor-area rich';
        area.contentEditable = 'true';
        area.setAttribute('role', 'textbox');
        area.setAttribute('aria-multiline', 'true');
        area.setAttribute('data-placeholder', textarea.getAttribute('placeholder') || '');
        if (textarea.id) { area.setAttribute('aria-labelledby', textarea.id + '-label'); }
        var status = document.createElement('div');
        status.className = 'editor-status';
        status.textContent = t('Formatting, tables and images are kept. Scripts are removed when saved.');

        var label = textarea.id ? $('label[for="' + textarea.id + '"]') : null;
        if (label) {
            label.id = textarea.id + '-label';
            // Clicking the label should focus the visual area, not the hidden textarea.
            label.addEventListener('click', function (e) { if (!textarea.hidden === false) { e.preventDefault(); area.focus(); } });
        }

        textarea.parentNode.insertBefore(wrap, textarea);
        wrap.appendChild(bar);
        wrap.appendChild(area);
        wrap.appendChild(textarea);
        wrap.appendChild(status);
        textarea.classList.add('editor-source');
        textarea.hidden = true;
        area.innerHTML = textarea.value.trim() && textarea.value.indexOf('<') === -1
            ? textarea.value.split(/\n{2,}/).map(function (p) { return '<p>' + escapeHtml(p).replace(/\n/g, '<br>') + '</p>'; }).join('')
            : textarea.value;

        try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* older browsers */ }
        try { document.execCommand('styleWithCSS', false, false); } catch (e) { /* older browsers */ }

        var sourceMode = false;
        var savedRange = null;
        var sync = function () {
            if (sourceMode) { return; }
            var html = area.innerHTML.trim();
            // Leave the field empty when nothing real was typed.
            if (!area.textContent.trim() && !area.querySelector('img, table, hr')) { html = ''; }
            textarea.value = html;
        };
        var saveRange = function () {
            var sel = window.getSelection();
            if (sel.rangeCount && area.contains(sel.getRangeAt(0).commonAncestorContainer)) { savedRange = sel.getRangeAt(0).cloneRange(); }
        };
        var restoreRange = function () {
            area.focus();
            if (savedRange) {
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedRange);
            }
        };
        var exec = function (cmd, value) {
            restoreRange();
            document.execCommand(cmd, false, value === undefined ? null : value);
            sync();
            refresh();
        };
        var insertHtml = function (html) { exec('insertHTML', html); };

        var currentCell = function () {
            var sel = window.getSelection();
            var node = sel.rangeCount ? sel.getRangeAt(0).startContainer : null;
            if (!node && savedRange) { node = savedRange.startContainer; }
            while (node && node !== area) {
                if (node.nodeType === 1 && (node.tagName === 'TD' || node.tagName === 'TH')) { return node; }
                node = node.parentNode;
            }
            return null;
        };

        var buttons = [];
        var addButton = function (group, text, title, action, opts) {
            var b = document.createElement('button');
            b.type = 'button';
            b.innerHTML = text;
            b.title = t(title);
            b.setAttribute('aria-label', t(title));
            b.addEventListener('mousedown', function (e) { e.preventDefault(); saveRange(); });
            b.addEventListener('click', function () { saveRange(); action(); });
            group.appendChild(b);
            buttons.push({ el: b, state: opts && opts.state, table: opts && opts.table, always: opts && opts.always });
            return b;
        };
        var newGroup = function () {
            var g = document.createElement('div');
            g.className = 'editor-group';
            bar.appendChild(g);
            return g;
        };

        // Block style
        var g = newGroup();
        var format = document.createElement('select');
        format.setAttribute('aria-label', t('Paragraph'));
        [['p', 'Paragraph'], ['h2', 'Heading 2'], ['h3', 'Heading 3'], ['h4', 'Heading 4'], ['blockquote', 'Quote'], ['pre', 'Code block']].forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o[0];
            opt.textContent = t(o[1]);
            format.appendChild(opt);
        });
        format.addEventListener('mousedown', saveRange);
        format.addEventListener('change', function () { exec('formatBlock', '<' + format.value + '>'); });
        g.appendChild(format);

        g = newGroup();
        addButton(g, '<b>B</b>', 'Bold', function () { exec('bold'); }, { state: 'bold' });
        addButton(g, '<i>I</i>', 'Italic', function () { exec('italic'); }, { state: 'italic' });
        addButton(g, '<u>U</u>', 'Underline', function () { exec('underline'); }, { state: 'underline' });
        addButton(g, '<s>S</s>', 'Strikethrough', function () { exec('strikeThrough'); }, { state: 'strikeThrough' });

        g = newGroup();
        addButton(g, '&bull;&#8212;', 'Bulleted list', function () { exec('insertUnorderedList'); }, { state: 'insertUnorderedList' });
        addButton(g, '1.&#8212;', 'Numbered list', function () { exec('insertOrderedList'); }, { state: 'insertOrderedList' });
        addButton(g, '&#8676;', 'Align left', function () { exec('justifyLeft'); }, { state: 'justifyLeft' });
        addButton(g, '&#8596;', 'Align center', function () { exec('justifyCenter'); }, { state: 'justifyCenter' });
        addButton(g, '&#8677;', 'Align right', function () { exec('justifyRight'); }, { state: 'justifyRight' });

        g = newGroup();
        addButton(g, '&#128279;', 'Insert link', function () {
            var url = window.prompt(t('Link address (https://...)'), 'https://');
            if (!url || url === 'https://') { return; }
            if (!/^(https?:|mailto:|tel:|\/)/i.test(url)) { url = 'https://' + url; }
            var sel = window.getSelection();
            restoreRange();
            if (sel.isCollapsed) {
                insertHtml('<a href="' + escapeAttr(url) + '" target="_blank">' + escapeHtml(url) + '</a>');
            } else {
                exec('createLink', url);
                $$('a[href="' + cssEscape(url) + '"]', area).forEach(function (a) { a.setAttribute('target', '_blank'); });
                sync();
            }
        });
        addButton(g, '&#10007;&#128279;', 'Remove link', function () { exec('unlink'); });
        addButton(g, '&#128247;', 'Insert image', function () { chooseImage(); });
        addButton(g, '&#8213;', 'Horizontal line', function () { exec('insertHorizontalRule'); });

        g = newGroup();
        addButton(g, '&#9638;', 'Insert table', function () {
            var rows = parseInt(window.prompt(t('How many rows?'), '3'), 10);
            var cols = parseInt(window.prompt(t('How many columns?'), '3'), 10);
            if (!(rows > 0 && cols > 0)) { return; }
            rows = Math.min(rows, 50);
            cols = Math.min(cols, 12);
            var html = '<table><thead><tr>';
            for (var c = 0; c < cols; c++) { html += '<th>' + escapeHtml(t('Column')) + ' ' + (c + 1) + '</th>'; }
            html += '</tr></thead><tbody>';
            for (var r = 0; r < rows - 1; r++) {
                html += '<tr>';
                for (c = 0; c < cols; c++) { html += '<td><br></td>'; }
                html += '</tr>';
            }
            html += '</tbody></table><p><br></p>';
            insertHtml(html);
        });
        addButton(g, '+&#8801;', 'Add row', function () {
            var cell = currentCell();
            if (!cell) { return; }
            var row = cell.parentNode;
            var copy = document.createElement('tr');
            for (var i = 0; i < row.cells.length; i++) { var td = document.createElement('td'); td.innerHTML = '<br>'; copy.appendChild(td); }
            if (row.parentNode.tagName === 'THEAD') {
                var body = row.closest('table').tBodies[0] || row.closest('table').createTBody();
                body.insertBefore(copy, body.firstChild);
            } else {
                row.parentNode.insertBefore(copy, row.nextSibling);
            }
            sync();
        }, { table: true });
        addButton(g, '+&#8214;', 'Add column', function () {
            var cell = currentCell();
            if (!cell) { return; }
            var index = cell.cellIndex;
            $$('tr', cell.closest('table')).forEach(function (tr) {
                var head = tr.parentNode.tagName === 'THEAD';
                var c = document.createElement(head ? 'th' : 'td');
                c.innerHTML = head ? escapeHtml(t('Column')) : '<br>';
                tr.insertBefore(c, tr.cells[index + 1] || null);
            });
            sync();
        }, { table: true });
        addButton(g, '&minus;&#8801;', 'Delete row', function () {
            var cell = currentCell();
            if (!cell) { return; }
            var table = cell.closest('table');
            cell.parentNode.remove();
            if (!table.rows.length) { table.remove(); }
            sync();
            refresh();
        }, { table: true });
        addButton(g, '&minus;&#8214;', 'Delete column', function () {
            var cell = currentCell();
            if (!cell) { return; }
            var table = cell.closest('table');
            var index = cell.cellIndex;
            $$('tr', table).forEach(function (tr) { if (tr.cells[index]) { tr.cells[index].remove(); } });
            if (!table.querySelector('td, th')) { table.remove(); }
            sync();
            refresh();
        }, { table: true });
        addButton(g, '&#10007;&#9638;', 'Delete table', function () {
            var cell = currentCell();
            if (!cell) { return; }
            cell.closest('table').remove();
            sync();
            refresh();
        }, { table: true });

        g = newGroup();
        addButton(g, '&#10226;', 'Undo', function () { exec('undo'); });
        addButton(g, '&#10227;', 'Redo', function () { exec('redo'); });
        addButton(g, 'T&#824;', 'Clear formatting', function () { exec('removeFormat'); exec('formatBlock', '<p>'); });

        g = newGroup();
        var sourceBtn = addButton(g, '&lt;/&gt;', 'HTML source', function () {
            sourceMode = !sourceMode;
            if (sourceMode) {
                sync();
                textarea.hidden = false;
                area.hidden = true;
                status.textContent = t('Editing HTML directly. Switch back to see the result.');
                textarea.focus();
            } else {
                area.innerHTML = textarea.value;
                textarea.hidden = true;
                area.hidden = false;
                status.textContent = t('Formatting, tables and images are kept. Scripts are removed when saved.');
                area.focus();
            }
            sourceBtn.title = t(sourceMode ? 'Visual editor' : 'HTML source');
            sourceBtn.classList.toggle('on', sourceMode);
            refresh();
        }, { always: true });

        // Images: upload from this computer, or paste an address.
        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/png,image/jpeg,image/gif,image/webp';
        fileInput.hidden = true;
        wrap.appendChild(fileInput);

        var uploadImage = function (file) {
            if (!uploadUrl || !file) { return; }
            var data = new FormData();
            data.append('image', file);
            status.classList.remove('bad');
            status.textContent = t('Uploading image...');
            fetch(uploadUrl, { method: 'POST', body: data, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } })
                .then(function (res) {
                    return res.json().then(function (json) {
                        if (!res.ok) { throw new Error(json.message || res.statusText); }
                        return json;
                    });
                })
                .then(function (json) {
                    insertHtml('<img src="' + escapeAttr(json.url) + '" alt="">');
                    status.textContent = t('Image uploaded.');
                })
                .catch(function (err) {
                    status.classList.add('bad');
                    status.textContent = t('Upload failed') + ': ' + err.message;
                });
        };
        fileInput.addEventListener('change', function () {
            uploadImage(fileInput.files[0]);
            fileInput.value = '';
        });
        var chooseImage = function () {
            if (uploadUrl && window.confirm(t('Upload a picture from this computer? Press Cancel to paste an image address instead.'))) {
                fileInput.click();
                return;
            }
            var url = window.prompt(t('Image address (https://...)'), 'https://');
            if (url && url !== 'https://') { insertHtml('<img src="' + escapeAttr(url) + '" alt="">'); }
        };

        // Pasted or dropped picture files are uploaded instead of being embedded as huge data: URLs.
        area.addEventListener('paste', function (e) {
            var files = e.clipboardData && e.clipboardData.files;
            if (files && files.length && /^image\//.test(files[0].type)) {
                e.preventDefault();
                saveRange();
                uploadImage(files[0]);
            }
        });
        area.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length && /^image\//.test(files[0].type)) {
                e.preventDefault();
                saveRange();
                uploadImage(files[0]);
            }
        });

        // Toolbar buttons light up for the formatting under the cursor; table buttons only work inside a table.
        var refresh = function () {
            var cell = sourceMode ? null : currentCell();
            $$('.is-current', area).forEach(function (c) { c.classList.remove('is-current'); });
            if (cell) { cell.classList.add('is-current'); }
            buttons.forEach(function (b) {
                if (b.always) { return; }
                b.el.disabled = sourceMode || (b.table && !cell);
                if (b.state) {
                    var on = false;
                    try { on = !sourceMode && document.queryCommandState(b.state); } catch (e) { /* unsupported */ }
                    b.el.classList.toggle('on', on);
                    b.el.setAttribute('aria-pressed', on ? 'true' : 'false');
                }
            });
            format.disabled = sourceMode;
            if (!sourceMode) {
                var block = '';
                try { block = String(document.queryCommandValue('formatBlock') || '').toLowerCase(); } catch (e) { /* unsupported */ }
                format.value = ['h2', 'h3', 'h4', 'blockquote', 'pre'].indexOf(block) !== -1 ? block : 'p';
            }
        };

        area.addEventListener('input', sync);
        area.addEventListener('keyup', function () { saveRange(); refresh(); });
        area.addEventListener('mouseup', function () { saveRange(); refresh(); });
        area.addEventListener('focus', refresh);
        document.addEventListener('selectionchange', function () { if (document.activeElement === area) { saveRange(); refresh(); } });

        // The "is-current" outline must never be saved.
        var form = textarea.form;
        if (form) {
            form.addEventListener('submit', function () {
                $$('.is-current', area).forEach(function (c) { c.classList.remove('is-current'); });
                if (!sourceMode) { sync(); }
            }, true);
        }

        refresh();
    });

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function escapeAttr(s) {
        return escapeHtml(s).replace(/"/g, '&quot;');
    }
    function cssEscape(s) {
        return window.CSS && CSS.escape ? CSS.escape(s) : String(s).replace(/"/g, '\\"');
    }

    // ----- Registration wizard -----
    var wizard = $('[data-wizard]');
    if (wizard) {
        var steps = $$('.wizard-step', wizard);
        var bars = $$('.steps i', wizard);
        var current = 0;

        // After a failed submit, open the first step that contains an error.
        steps.forEach(function (step, i) {
            if (current === 0 && $('.is-invalid', step) && !$('.is-invalid', steps[0])) { current = i; }
        });

        var show = function (index) {
            current = Math.max(0, Math.min(steps.length - 1, index));
            steps.forEach(function (step, i) { step.hidden = i !== current; });
            bars.forEach(function (bar, i) { bar.classList.toggle('done', i <= current); });
            var first = $('input, select, textarea', steps[current]);
            if (first) { first.focus({ preventScroll: true }); }
            wizard.scrollIntoView({ block: 'start', behavior: 'smooth' });
        };

        var validStep = function () {
            var ok = true;
            $$('input, select, textarea', steps[current]).forEach(function (field) {
                if (ok && !field.checkValidity()) { field.reportValidity(); ok = false; }
            });
            if (ok) {
                var pw = $('#password', steps[current]);
                var confirm = $('#password_confirmation', steps[current]);
                if (pw && confirm && pw.value !== confirm.value) {
                    confirm.setCustomValidity('The passwords do not match.');
                    confirm.reportValidity();
                    confirm.addEventListener('input', function () { confirm.setCustomValidity(''); }, { once: true });
                    ok = false;
                }
            }
            return ok;
        };

        wizard.addEventListener('click', function (e) {
            if (e.target.closest('[data-next]')) { if (validStep()) { show(current + 1); } }
            if (e.target.closest('[data-prev]')) { show(current - 1); }
        });

        // Enter moves to the next step instead of submitting half a form.
        wizard.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && current < steps.length - 1) {
                e.preventDefault();
                if (validStep()) { show(current + 1); }
            }
        });

        show(current);
    }
})();
