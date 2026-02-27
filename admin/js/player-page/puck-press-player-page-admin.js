(function ($) {
    $(document).ready(function () {
        const config   = ppPlayerPageAdmin;
        const iframe   = document.getElementById('pp-pd-preview-iframe');
        const playerSelect = document.getElementById('pp-pd-player-select');
        const saveBtn  = document.getElementById('pp-pd-save');
        const saveMsg  = document.getElementById('pp-pd-save-msg');

        // ── Mutable state ────────────────────────────────────────
        const currentColors = Object.assign({}, config.colors);
        let   currentFont   = config.font || '';

        // ── Build color inputs ────────────────────────────────────
        const colorContainer = document.getElementById('pp-pd-color-fields');
        Object.entries(config.colorLabels).forEach(([key, label]) => {
            const val = currentColors[key] || '#000000';
            colorContainer.insertAdjacentHTML('beforeend', `
                <div class="pp-pd-color-row">
                    <label class="pp-pd-color-label">${label}</label>
                    <div class="pp-pd-color-inputs">
                        <input type="color"  class="pp-pd-swatch" data-key="${key}" value="${val}">
                        <input type="text"   class="pp-pd-hex"    data-key="${key}" value="${val}" maxlength="7" spellcheck="false">
                    </div>
                </div>`);
        });

        // ── Build font dropdown ───────────────────────────────────
        const fontContainer = document.getElementById('pp-pd-font-fields');
        let fontOptions = '<option value="">— Theme Default —</option>';
        (window.ppGoogleFonts || []).forEach((font) => {
            fontOptions += `<option value="${font}"${font === currentFont ? ' selected' : ''}>${font}</option>`;
        });
        fontContainer.insertAdjacentHTML('beforeend', `
            <div class="pp-font-form-group">
                <label class="pp-form-label">${config.fontLabel}</label>
                <div class="pp-font-input-group">
                    <select id="pp-pd-font-select" class="pp-font-value">${fontOptions}</select>
                </div>
            </div>`);
        $('#pp-pd-font-select').select2({ width: '100%' });
        if (currentFont && window.ppLoadGoogleFont) window.ppLoadGoogleFont(currentFont);

        // ── Apply all current colors/font to the iframe ───────────
        function applyToIframe() {
            if (!iframe || !iframe.contentWindow) return;
            try {
                const root = iframe.contentWindow.document.documentElement;
                Object.entries(currentColors).forEach(([k, v]) => {
                    root.style.setProperty(`--pp-pd-${k}`, v);
                });
                if (currentFont) {
                    const safe = currentFont.replace(/['";}]/g, '');
                    root.style.setProperty('--pp-pd-font-family', `'${safe}', sans-serif`);
                }
            } catch (_) {}
        }

        // ── Load iframe with first player ────────────────────────
        if (playerSelect && playerSelect.value) {
            iframe.src = playerSelect.value;
        }
        iframe.addEventListener('load', applyToIframe);

        // ── Color swatch ↔ hex input sync ────────────────────────
        $(document).on('input', '.pp-pd-swatch', function () {
            const key = $(this).data('key');
            const val = $(this).val();
            currentColors[key] = val;
            $(`.pp-pd-hex[data-key="${key}"]`).val(val);
            applyToIframe();
        });

        $(document).on('input', '.pp-pd-hex', function () {
            const key = $(this).data('key');
            const val = $(this).val();
            if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                currentColors[key] = val;
                $(`.pp-pd-swatch[data-key="${key}"]`).val(val);
                applyToIframe();
            }
        });

        // ── Font dropdown ─────────────────────────────────────────
        $(document).on('change', '#pp-pd-font-select', function () {
            currentFont = $(this).val();
            if (currentFont && window.ppLoadGoogleFont) window.ppLoadGoogleFont(currentFont);
            try {
                const root = iframe.contentWindow.document.documentElement;
                if (currentFont) {
                    const safe = currentFont.replace(/['";}]/g, '');
                    root.style.setProperty('--pp-pd-font-family', `'${safe}', sans-serif`);
                } else {
                    root.style.removeProperty('--pp-pd-font-family');
                }
            } catch (_) {}
        });

        // ── Player switcher ───────────────────────────────────────
        if (playerSelect) {
            $(playerSelect).on('change', function () {
                iframe.src = $(this).val();
            });
        }

        // ── Save ──────────────────────────────────────────────────
        if (saveBtn) {
            $(saveBtn).on('click', function () {
                $(saveBtn).prop('disabled', true).text('Saving…');
                $(saveMsg).text('').removeClass('pp-save-msg--ok pp-save-msg--err');

                const postData = {
                    action: 'puck_press_update_player_detail_colors',
                    colors: currentColors,
                    fonts:  { 'player-font': currentFont },
                };

                $.post(config.ajaxUrl, postData, function (resp) {
                    if (resp.success) {
                        $(saveMsg).text('Saved!').addClass('pp-save-msg--ok').show();
                    } else {
                        $(saveMsg).text('Error saving.').addClass('pp-save-msg--err').show();
                    }
                    setTimeout(() => $(saveMsg).fadeOut(), 2500);
                }).always(() => {
                    $(saveBtn).prop('disabled', false).text('Save Colors');
                });
            });
        }
    });
})(jQuery);
