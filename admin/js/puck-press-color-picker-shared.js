(function (window, $) {

    var GOOGLE_FONTS = [
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
        var id = 'pp-admin-gf-' + fontName.replace(/\s+/g, '-').toLowerCase();
        if (document.getElementById(id)) return;
        var link = document.createElement('link');
        link.id  = id;
        link.rel = 'stylesheet';
        link.href = 'https://fonts.googleapis.com/css2?family='
            + encodeURIComponent(fontName)
            + ':wght@400;600;700;800&display=swap';
        document.head.appendChild(link);
    }

    function fontFamilyCss(fontName) {
        return fontName ? "'" + fontName + "', sans-serif" : 'inherit';
    }

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

        var hasFont = !!cfg.fontFieldsContainerId;
        var $modal  = $(cfg.modalId);
        var $form   = $(cfg.formId);

        // ── Open ─────────────────────────────────────────────────────────────

        $(cfg.openBtnId).on('click', function () {
            $modal.css('display', 'flex');
            var key = $(cfg.templateSelectorId).val();
            generateColorFields(key);
            if (hasFont) generateFontFields(key);
        });

        // ── Close / cancel / click-outside ───────────────────────────────────

        function closeModal() {
            $modal.css('display', 'none');
            $form[0].reset();
            populateColorInputsFromTemplates();
            resetTemplateRendering();
            $(cfg.templateSelectorId).val(cfg.templatesData.selected_template);
        }

        $(cfg.closeBtnId).on('click', closeModal);
        $(cfg.cancelBtnId).on('click', closeModal);
        $modal.on('click', function (e) { if (e.target === this) closeModal(); });

        // ── Save ─────────────────────────────────────────────────────────────

        $(cfg.saveBtnId).on('click', function () {
            if (!$form[0].checkValidity()) { $form[0].reportValidity(); return; }

            var key    = $(cfg.templateSelectorId).val();
            var colors = getColorSettings();
            var data   = { action: cfg.ajaxAction, template_key: key, colors: colors };
            if (hasFont) data.fonts = getFontSettings();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        cfg.templatesData[cfg.templatesKey][key] = colors;
                        if (hasFont) cfg.templatesData.fontSettings[key] = data.fonts;
                        cfg.templatesData.selected_template = key;
                        generateColorFields(key);
                        if (hasFont) generateFontFields(key);
                    } else {
                        console.log(response.data);
                    }
                },
                error: function () {
                    console.log('AJAX error saving colors');
                }
            });
        });

        // ── Color fields ─────────────────────────────────────────────────────

        function getColorSettings() {
            var settings = {};
            $(cfg.colorFieldsContainerId + ' .pp-palette-form-group').each(function () {
                var key   = $(this).data('color-key');
                var value = $(this).find('.pp-color-pallette-color-value').val().trim();
                settings[key] = value;
            });
            return settings;
        }

        function generateColorFields(templateKey) {
            var template = cfg.templatesData[cfg.templatesKey][templateKey];
            if (!template) return;

            var labels = ((cfg.templatesData.colorLabels || {})[templateKey]) || {};
            $(cfg.colorFieldsContainerId).empty();

            Object.entries(template).forEach(function (entry) {
                var colorKey   = entry[0];
                var colorValue = entry[1];
                var colorLabel = labels[colorKey]
                    || colorKey.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });

                $(cfg.colorFieldsContainerId).append(
                    '<div class="pp-palette-form-group" data-color-key="' + colorKey + '">' +
                        '<label for="pp-' + templateKey + '-' + colorKey + '-color" class="pp-form-label">' + colorLabel + '</label>' +
                        '<div class="pp-color-pallette-color-input-group">' +
                            '<input type="color" class="pp-color-pallette-color-preview"' +
                                   ' id="pp-' + templateKey + '-' + colorKey + '-color-picker-input" value="' + colorValue + '">' +
                            '<input type="text" class="pp-color-pallette-color-value"' +
                                   ' id="pp-' + templateKey + '-' + colorKey + '-color-text-input" value="' + colorValue + '">' +
                        '</div>' +
                    '</div>'
                );
            });

            // picker ↔ text sync
            $('.pp-color-pallette-color-preview').off('input').on('input', function () {
                var textId = $(this).attr('id').replace('-picker-', '-text-');
                $('#' + textId).val($(this).val()).trigger('input');
            });
            $('.pp-color-pallette-color-value').off('input').on('input', function () {
                var pickerId = $(this).attr('id').replace('-text-', '-picker-');
                $('#' + pickerId).val($(this).val());
            });
        }

        // ── Font fields (only when hasFont = true) ───────────────────────────

        function getFontSettings() {
            var settings = {};
            $(cfg.fontFieldsContainerId + ' .pp-font-form-group').each(function () {
                settings[$(this).data('font-key')] = ($(this).find('.pp-font-value').val() || '').trim();
            });
            return settings;
        }

        function generateFontFields(templateKey) {
            var fonts     = ((cfg.templatesData.fontSettings || {})[templateKey]) || {};
            var labels    = ((cfg.templatesData.fontLabels   || {})[templateKey]) || {};
            var $container = $(cfg.fontFieldsContainerId);

            $container.find('select.pp-font-value').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) $(this).select2('destroy');
            });
            $container.empty();

            Object.entries(fonts).forEach(function (entry) {
                var fontKey   = entry[0];
                var fontValue = entry[1];
                var fontLabel = labels[fontKey]
                    || fontKey.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });

                var options = '<option value="">— Theme Default —</option>';
                GOOGLE_FONTS.forEach(function (font) {
                    options += '<option value="' + font + '"' + (font === fontValue ? ' selected' : '') + '>' + font + '</option>';
                });

                $container.append(
                    '<div class="pp-font-form-group" data-font-key="' + fontKey + '">' +
                        '<label class="pp-form-label">' + fontLabel + '</label>' +
                        '<div class="pp-font-input-group">' +
                            '<select class="pp-font-value" id="pp-' + templateKey + '-' + fontKey + '-font-select">' +
                                options +
                            '</select>' +
                        '</div>' +
                    '</div>'
                );

                var $select = $('#pp-' + templateKey + '-' + fontKey + '-font-select');
                $select.select2({ dropdownParent: $modal, width: '100%' });

                if (fontValue) loadGoogleFont(fontValue);

                $select.on('select2:select', function () {
                    var fontName = ($(this).val() || '').trim();
                    var cssValue = fontFamilyCss(fontName);
                    document.documentElement.style.setProperty('--pp-' + templateKey + '-' + fontKey, cssValue);
                    $('.' + templateKey + cfg.containerSuffix).css('font-family', cssValue);
                    if (cfg.onFontChange) cfg.onFontChange(templateKey, fontKey, cssValue);
                    if (fontName) loadGoogleFont(fontName);
                });
            });
        }

        // ── Template selector ────────────────────────────────────────────────

        $(cfg.templateSelectorId).on('change', function () {
            var key = $(this).val();
            generateColorFields(key);
            if (hasFont) generateFontFields(key);
            for (var k in cfg.templatesData[cfg.templatesKey]) {
                $('.' + k + cfg.containerSuffix).hide();
            }
            $('.' + key + cfg.containerSuffix).show();
        });

        // ── Init CSS vars on page load ────────────────────────────────────────

        $.each(cfg.templatesData[cfg.templatesKey], function (templateKey, colorSet) {
            $.each(colorSet, function (colorKey, defaultValue) {
                var inputId = '#pp-' + templateKey + '-' + colorKey + '-color-text-input';
                var cssVar  = '--pp-' + templateKey + '-' + colorKey;
                document.documentElement.style.setProperty(cssVar, $(inputId).val() || defaultValue);
                $(document).on('input', inputId, function () {
                    document.documentElement.style.setProperty(cssVar, $(this).val());
                });
            });
        });

        if (hasFont) {
            $.each(cfg.templatesData.fontSettings || {}, function (templateKey, fontSet) {
                $.each(fontSet, function (fontKey, fontValue) {
                    if (!fontValue) return;
                    var cssValue = fontFamilyCss(fontValue);
                    document.documentElement.style.setProperty('--pp-' + templateKey + '-' + fontKey, cssValue);
                    $('.' + templateKey + cfg.containerSuffix).css('font-family', cssValue);
                    if (cfg.onFontChange) cfg.onFontChange(templateKey, fontKey, cssValue);
                    loadGoogleFont(fontValue);
                });
            });
        }

        // ── Helpers ───────────────────────────────────────────────────────────

        function populateColorInputsFromTemplates() {
            $.each(cfg.templatesData[cfg.templatesKey], function (templateKey, colorSet) {
                $.each(colorSet, function (colorKey, value) {
                    $('#pp-' + templateKey + '-' + colorKey + '-color-picker-input').val(value);
                    $('#pp-' + templateKey + '-' + colorKey + '-color-text-input').val(value.toUpperCase());
                    document.documentElement.style.setProperty('--pp-' + templateKey + '-' + colorKey, value);
                });
            });
        }

        function resetTemplateRendering() {
            for (var k in cfg.templatesData[cfg.templatesKey]) {
                $('.' + k + cfg.containerSuffix).hide();
            }
            $('.' + cfg.templatesData.selected_template + cfg.containerSuffix).show();
        }

    };

})(window, jQuery);
