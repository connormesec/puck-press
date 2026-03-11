(function (window, $) {

    const GOOGLE_FONTS = [
        'Anton',
        'Barlow',
        'Barlow Condensed',
        'Bebas Neue',
        'Exo 2',
        'Fjalla One',
        'Inter',
        'Lato',
        'Merriweather',
        'Montserrat',
        'Nunito',
        'Open Sans',
        'Oswald',
        'Playfair Display',
        'Poppins',
        'PT Sans',
        'Raleway',
        'Roboto',
        'Roboto Condensed',
        'Russo One',
        'Source Sans 3',
        'Teko',
        'Ubuntu',
    ];

    window.ppGoogleFonts = GOOGLE_FONTS;

    const loadGoogleFont = (fontName) => {
        const id = `pp-admin-gf-${fontName.replace(/\s+/g, '-').toLowerCase()}`;
        if (document.getElementById(id)) return;
        const link = document.createElement('link');
        link.id  = id;
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(fontName)}:wght@400;600;700;800&display=swap`;
        document.head.appendChild(link);
    };

    window.ppLoadGoogleFont = loadGoogleFont;

    const fontFamilyCss = (fontName) =>
        fontName ? `'${fontName}', sans-serif` : 'inherit';

    /**
     * Creates a colour-palette modal controller.
     *
     * @param {object} cfg
     *   modalId, openBtnId, closeBtnId, cancelBtnId, saveBtnId, formId
     *   colorFieldsContainerId, templateSelectorId
     *   templatesData      — the wp_localize_script JS global (e.g. ppScheduleTemplates)
     *   templatesKey       — property inside templatesData that holds the color map
     *   ajaxAction         — WordPress AJAX action string
     *   containerSuffix    — CSS class suffix for preview containers (e.g. '_schedule_container')
     *   fontFieldsContainerId  (optional) — omit for no font support
     *   onFontChange(templateKey, fontKey, cssValue)  (optional) — extra CSS var hook
     */
    window.createColorPickerController = function (cfg) {

        const hasFont = !!cfg.fontFieldsContainerId;
        const $modal  = $(cfg.modalId);
        const $form   = $(cfg.formId);
        let isSaving  = false;

        const updateCalUrlVisibility = (templateKey) => {
            if (!cfg.calUrlFieldId || !cfg.calUrlShowForTemplates) return;
            const $row = $(cfg.calUrlFieldId).closest('.pp-form-row');
            if (cfg.calUrlShowForTemplates.includes(templateKey)) {
                $row.show();
            } else {
                $row.hide();
            }
        };

        // ── Open ─────────────────────────────────────────────────────────────

        $(cfg.openBtnId).on('click', () => {
            $modal.css('display', 'flex');
            $(cfg.templateSelectorId).val(cfg.templatesData.selected_template);
            const key = $(cfg.templateSelectorId).val();
            generateColorFields(key);
            if (hasFont) generateFontFields(key);
            if (cfg.calUrlFieldId) $(cfg.calUrlFieldId).val(cfg.templatesData.cal_url || '');
            updateCalUrlVisibility(key);
        });

        // ── Close / cancel / click-outside ───────────────────────────────────

        const closeModal = () => {
            $modal.css('display', 'none');
            $form[0].reset();
            populateColorInputsFromTemplates();
            resetTemplateRendering();
            $(cfg.templateSelectorId).val(cfg.templatesData.selected_template);
        };

        $(cfg.closeBtnId).on('click', closeModal);
        $(cfg.cancelBtnId).on('click', closeModal);
        $modal.on('click', function (e) { if (e.target === this && !isSaving) closeModal(); });

        // ── Save ─────────────────────────────────────────────────────────────

        $(cfg.saveBtnId).on('click', () => {
            if (!$form[0].checkValidity()) { $form[0].reportValidity(); return; }
            if (isSaving) return;

            isSaving = true;
            $(cfg.saveBtnId).prop('disabled', true).text('Saving…');

            const key    = $(cfg.templateSelectorId).val();
            const colors = getColorSettings();
            const data   = { action: cfg.ajaxAction, template_key: key, colors: colors };
            if (hasFont) data.fonts = getFontSettings();
            if (cfg.calUrlFieldId) data.cal_url = $(cfg.calUrlFieldId).val().trim();
            if (cfg.extraData) Object.assign(data, (typeof cfg.extraData === 'function' ? cfg.extraData() : cfg.extraData));

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        cfg.templatesData[cfg.templatesKey][key] = colors;
                        if (hasFont) cfg.templatesData.fontSettings[key] = data.fonts;
                        if (cfg.calUrlFieldId) cfg.templatesData.cal_url = data.cal_url || '';
                        cfg.templatesData.selected_template = key;
                        generateColorFields(key);
                        if (hasFont) generateFontFields(key);
                    } else {
                        console.log(response.data);
                    }
                },
                error: () => {
                    console.log('AJAX error saving colors');
                },
                complete: () => {
                    isSaving = false;
                    $(cfg.saveBtnId).prop('disabled', false).text('Save Colors');
                }
            });
        });

        // ── Color fields ─────────────────────────────────────────────────────

        const getColorSettings = () => {
            const settings = {};
            $(cfg.colorFieldsContainerId + ' .pp-palette-form-group').each(function () {
                const key   = $(this).data('color-key');
                const value = $(this).find('.pp-color-pallette-color-value').val().trim();
                settings[key] = value;
            });
            return settings;
        };

        const generateColorFields = (templateKey) => {
            const template = cfg.templatesData[cfg.templatesKey][templateKey];
            if (!template) return;

            const labels = ((cfg.templatesData.colorLabels || {})[templateKey]) || {};
            $(cfg.colorFieldsContainerId).empty();

            Object.entries(template).forEach(([colorKey, colorValue]) => {
                const colorLabel = labels[colorKey]
                    || colorKey.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

                $(cfg.colorFieldsContainerId).append(
                    `<div class="pp-palette-form-group" data-color-key="${colorKey}">` +
                        `<label for="pp-${templateKey}-${colorKey}-color" class="pp-form-label">${colorLabel}</label>` +
                        '<div class="pp-color-pallette-color-input-group">' +
                            `<input type="color" class="pp-color-pallette-color-preview"` +
                                   ` id="pp-${templateKey}-${colorKey}-color-picker-input" value="${colorValue}">` +
                            `<input type="text" class="pp-color-pallette-color-value"` +
                                   ` id="pp-${templateKey}-${colorKey}-color-text-input" value="${colorValue}">` +
                        '</div>' +
                    '</div>'
                );
            });

            // picker ↔ text sync
            $('.pp-color-pallette-color-preview').off('input').on('input', function () {
                const textId = $(this).attr('id').replace('-picker-', '-text-');
                $('#' + textId).val($(this).val()).trigger('input');
            });
            $('.pp-color-pallette-color-value').off('input').on('input', function () {
                const pickerId = $(this).attr('id').replace('-text-', '-picker-');
                $('#' + pickerId).val($(this).val());
            });
        };

        // ── Font fields (only when hasFont = true) ───────────────────────────

        const getFontSettings = () => {
            const settings = {};
            $(cfg.fontFieldsContainerId + ' .pp-font-form-group').each(function () {
                settings[$(this).data('font-key')] = ($(this).find('.pp-font-value').val() || '').trim();
            });
            return settings;
        };

        const generateFontFields = (templateKey) => {
            const fonts     = ((cfg.templatesData.fontSettings || {})[templateKey]) || {};
            const labels    = ((cfg.templatesData.fontLabels   || {})[templateKey]) || {};
            const $container = $(cfg.fontFieldsContainerId);

            $container.find('select.pp-font-value').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
            });
            $container.empty();

            Object.entries(fonts).forEach(([fontKey, fontValue]) => {
                const fontLabel = labels[fontKey]
                    || fontKey.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

                let options = '<option value="">— Theme Default —</option>';
                GOOGLE_FONTS.forEach((font) => {
                    options += `<option value="${font}"${font === fontValue ? ' selected' : ''}>${font}</option>`;
                });

                $container.append(
                    `<div class="pp-font-form-group" data-font-key="${fontKey}">` +
                        `<label class="pp-form-label">${fontLabel}</label>` +
                        '<div class="pp-font-input-group">' +
                            `<select class="pp-font-value" id="pp-${templateKey}-${fontKey}-font-select">` +
                                options +
                            '</select>' +
                        '</div>' +
                    '</div>'
                );

                const $select = $(`#pp-${templateKey}-${fontKey}-font-select`);
                $select.select2({ dropdownParent: $modal, width: '100%' });

                if (fontValue) loadGoogleFont(fontValue);

                $select.on('select2:select change', function () {
                    const fontName = ($(this).val() || '').trim();
                    const cssValue = fontFamilyCss(fontName);
                    document.documentElement.style.setProperty(`--pp-${templateKey}-${fontKey}`, cssValue);
                    $(`.${templateKey}${cfg.containerSuffix}`).css('font-family', cssValue);
                    if (cfg.onFontChange) cfg.onFontChange(templateKey, fontKey, cssValue);
                    if (fontName) loadGoogleFont(fontName);
                });
            });
        };

        // ── Template selector ────────────────────────────────────────────────

        $(cfg.templateSelectorId).on('change', function () {
            const key = $(this).val();
            generateColorFields(key);
            if (hasFont) generateFontFields(key);
            updateCalUrlVisibility(key);
            for (const k of Object.keys(cfg.templatesData[cfg.templatesKey])) {
                $('.' + k + cfg.containerSuffix).hide();
            }
            $('.' + key + cfg.containerSuffix).show();
        });

        // ── Init CSS vars on page load ────────────────────────────────────────

        Object.entries(cfg.templatesData[cfg.templatesKey]).forEach(([templateKey, colorSet]) => {
            Object.entries(colorSet).forEach(([colorKey, defaultValue]) => {
                const inputId = `#pp-${templateKey}-${colorKey}-color-text-input`;
                const cssVar  = `--pp-${templateKey}-${colorKey}`;
                document.documentElement.style.setProperty(cssVar, $(inputId).val() || defaultValue);
                $(document).on('input', inputId, function () {
                    document.documentElement.style.setProperty(cssVar, $(this).val());
                });
            });
        });

        if (hasFont) {
            Object.entries(cfg.templatesData.fontSettings || {}).forEach(([templateKey, fontSet]) => {
                Object.entries(fontSet).forEach(([fontKey, fontValue]) => {
                    if (!fontValue) return;
                    const cssValue = fontFamilyCss(fontValue);
                    document.documentElement.style.setProperty(`--pp-${templateKey}-${fontKey}`, cssValue);
                    $(`.${templateKey}${cfg.containerSuffix}`).css('font-family', cssValue);
                    if (cfg.onFontChange) cfg.onFontChange(templateKey, fontKey, cssValue);
                    loadGoogleFont(fontValue);
                });
            });
        }

        // ── Helpers ───────────────────────────────────────────────────────────

        const populateColorInputsFromTemplates = () => {
            Object.entries(cfg.templatesData[cfg.templatesKey]).forEach(([templateKey, colorSet]) => {
                Object.entries(colorSet).forEach(([colorKey, value]) => {
                    $(`#pp-${templateKey}-${colorKey}-color-picker-input`).val(value);
                    $(`#pp-${templateKey}-${colorKey}-color-text-input`).val(value.toUpperCase());
                    document.documentElement.style.setProperty(`--pp-${templateKey}-${colorKey}`, value);
                });
            });
        };

        const resetTemplateRendering = () => {
            for (const k of Object.keys(cfg.templatesData[cfg.templatesKey])) {
                $('.' + k + cfg.containerSuffix).hide();
            }
            $('.' + cfg.templatesData.selected_template + cfg.containerSuffix).show();
        };

        // On page load, show only the currently selected template in the preview.
        resetTemplateRendering();

    };

})(window, jQuery);
