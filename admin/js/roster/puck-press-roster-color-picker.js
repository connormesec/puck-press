(function ($) {
    jQuery(document).ready(function ($) {
        const $paletteModal = $('#pp-roster-paletteModal');
        const $openBtn = $('#pp-roster-colorPaletteBtn');
        const $closeBtn = $('#pp-roster-palette-modal-close');
        const $cancelBtn = $('#pp-cancel-save-roster-colors');
        const $saveBtn = $('#pp-roster-palette-save-colors');
        const $form = $('#pp-roster-color-palette-form');

        $openBtn.on('click', function () {
            $paletteModal.css('display', 'flex');
            const templateKey = $('#pp-roster-template-selector').val();
            generateColorFields(templateKey);
            generateFontFields(templateKey);
        });

        function closeModal() {
            $paletteModal.css('display', 'none');
            $form[0].reset();
            populateColorInputsFromTemplates();
            resetTemplateRendering();
            $('#pp-roster-template-selector').val(ppRosterTemplates.selected_template);
        }

        $closeBtn.on('click', closeModal);
        $cancelBtn.on('click', closeModal);
        $paletteModal.on('click', function (e) {
            if (e.target === this) closeModal();
        });

        $saveBtn.on('click', function () {
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            const templateKey = $('#pp-roster-template-selector').val();
            const colorSettings = getColorSettings();
            const fontSettings  = getFontSettings();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'puck_press_update_roster_colors',
                    template_key: templateKey,
                    colors: colorSettings,
                    fonts:  fontSettings
                },
                success: function (response) {
                    if (response.success) {
                        ppRosterTemplates.rosterTemplates[templateKey] = colorSettings;
                        ppRosterTemplates.fontSettings[templateKey]    = fontSettings;
                        ppRosterTemplates.selected_template = templateKey;
                        generateColorFields(templateKey);
                        generateFontFields(templateKey);
                    } else {
                        console.log(response.data);
                    }
                },
                error: function () {
                    console.log('AJAX error');
                }
            });
        });

        // ── Color fields ─────────────────────────────────────────────────────

        function getColorSettings() {
            const settings = {};
            $('#pp-roster-dynamic-color-fields .pp-palette-form-group').each(function () {
                const colorKey = $(this).data('color-key');
                const value = $(this).find('.pp-color-pallette-color-value').val().trim();
                settings[colorKey] = value;
            });
            return settings;
        }

        function generateColorFields(templateKey) {
            const template = ppRosterTemplates.rosterTemplates[templateKey];
            if (!template) return;
            const labels = (ppRosterTemplates.colorLabels && ppRosterTemplates.colorLabels[templateKey]) || {};
            $('#pp-roster-dynamic-color-fields').empty();
            Object.entries(template).forEach(([colorKey, colorValue]) => {
                const colorLabel = labels[colorKey]
                    || colorKey.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                const field = `
                    <div class="pp-palette-form-group" data-color-key="${colorKey}">
                        <label for="pp-${templateKey}-${colorKey}-color" class="pp-form-label">${colorLabel}</label>
                        <div class="pp-color-pallette-color-input-group">
                            <input type="color" class="pp-color-pallette-color-preview"
                                   id="pp-${templateKey}-${colorKey}-color-picker-input" value="${colorValue}">
                            <input type="text" class="pp-color-pallette-color-value"
                                   id="pp-${templateKey}-${colorKey}-color-text-input" value="${colorValue}">
                        </div>
                    </div>
                `;
                $('#pp-roster-dynamic-color-fields').append(field);
            });

            $('.pp-color-pallette-color-preview').off('input').on('input', function () {
                const value = $(this).val();
                const textId = $(this).attr('id').replace('-picker-', '-text-');
                $('#' + textId).val(value).trigger('input');
            });

            $('.pp-color-pallette-color-value').off('input').on('input', function () {
                const value = $(this).val();
                const pickerId = $(this).attr('id').replace('-text-', '-picker-');
                $('#' + pickerId).val(value);
            });
        }

        // ── Font fields ──────────────────────────────────────────────────────

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

        function loadGoogleFont(fontName) {
            const id = 'pp-admin-gf-' + fontName.replace(/\s+/g, '-').toLowerCase();
            if (document.getElementById(id)) return;
            const link = document.createElement('link');
            link.id  = id;
            link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family='
                + encodeURIComponent(fontName)
                + ':wght@400;600;700;800&display=swap';
            document.head.appendChild(link);
        }

        function getFontSettings() {
            const settings = {};
            $('#pp-roster-dynamic-font-fields .pp-font-form-group').each(function () {
                const fontKey = $(this).data('font-key');
                const value   = ($(this).find('.pp-font-value').val() || '').trim();
                settings[fontKey] = value;
            });
            return settings;
        }

        function fontFamilyCss(fontName) {
            return fontName ? `'${fontName}', sans-serif` : 'inherit';
        }

        function generateFontFields(templateKey) {
            const fonts  = (ppRosterTemplates.fontSettings && ppRosterTemplates.fontSettings[templateKey]) || {};
            const labels = (ppRosterTemplates.fontLabels   && ppRosterTemplates.fontLabels[templateKey])   || {};
            const $container = $('#pp-roster-dynamic-font-fields');

            // Destroy any existing Select2 instances before clearing
            $container.find('select.pp-font-value').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
            });
            $container.empty();

            Object.entries(fonts).forEach(([fontKey, fontValue]) => {
                const fontLabel = labels[fontKey]
                    || fontKey.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

                let options = '<option value="">— Theme Default —</option>';
                GOOGLE_FONTS.forEach(font => {
                    const sel = font === fontValue ? ' selected' : '';
                    options += `<option value="${font}"${sel}>${font}</option>`;
                });

                const field = `
                    <div class="pp-font-form-group" data-font-key="${fontKey}">
                        <label class="pp-form-label">${fontLabel}</label>
                        <div class="pp-font-input-group">
                            <select class="pp-font-value"
                                    id="pp-${templateKey}-${fontKey}-font-select">
                                ${options}
                            </select>
                        </div>
                    </div>
                `;
                $container.append(field);

                const $select = $(`#pp-${templateKey}-${fontKey}-font-select`);

                $select.select2({
                    dropdownParent: $paletteModal,
                    width: '100%',
                });

                // Pre-load font in admin so preview renders immediately on modal open
                if (fontValue) loadGoogleFont(fontValue);

                // Live preview via Select2's own event — avoids stripping Select2's
                // internal native change handler which drives its display update.
                $select.on('select2:select', function () {
                    const fontName = ($(this).val() || '').trim();
                    const cssValue = fontFamilyCss(fontName);
                    document.documentElement.style.setProperty(`--pp-${templateKey}-${fontKey}`, cssValue);
                    document.documentElement.style.setProperty('--pp-pd-font-family', cssValue);
                    if (fontName) loadGoogleFont(fontName);
                });
            });
        }

        // ── Template selector ────────────────────────────────────────────────

        $('#pp-roster-template-selector').on('change', function () {
            const selected = $(this).val();
            generateColorFields(selected);
            generateFontFields(selected);
            for (let key in ppRosterTemplates.rosterTemplates) {
                $(`.${key}_roster_container`).hide();
            }
            $(`.${selected}_roster_container`).show();
        });

        // ── Initial CSS var setup (live preview before any modal interaction) ─

        $.each(ppRosterTemplates.rosterTemplates, function (templateKey, colorSet) {
            $.each(colorSet, function (colorKey, defaultValue) {
                const inputId = `#pp-${templateKey}-${colorKey}-color-text-input`;
                const cssVar = `--pp-${templateKey}-${colorKey}`;
                const value = $(inputId).val() || defaultValue;
                document.documentElement.style.setProperty(cssVar, value);
                $(document).on('input', inputId, function () {
                    document.documentElement.style.setProperty(cssVar, $(this).val());
                });
            });
        });

        function populateColorInputsFromTemplates() {
            $.each(ppRosterTemplates.rosterTemplates, function (templateKey, colorSet) {
                $.each(colorSet, function (colorKey, value) {
                    $(`#pp-${templateKey}-${colorKey}-color-picker-input`).val(value);
                    $(`#pp-${templateKey}-${colorKey}-color-text-input`).val(value.toUpperCase());
                    document.documentElement.style.setProperty(`--pp-${templateKey}-${colorKey}`, value);
                });
            });
        }

        function resetTemplateRendering() {
            for (let key in ppRosterTemplates.rosterTemplates) {
                $(`.${key}_roster_container`).hide();
            }
            $(`.${ppRosterTemplates.selected_template}_roster_container`).show();
        }
    });
})(jQuery);
