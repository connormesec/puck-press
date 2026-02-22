(function ($) {
    jQuery(document).ready(function ($) {
        //############################################################//
        //                                                            //
        //                Schedule Color Palette functionality        //
        //                                                            //
        //############################################################//
        const $scheduleColorPaletteModal = $('#pp-schedule-paletteModal');
        const $scheduleColorPaletteBtn = $('#pp-schedule-colorPaletteBtn');
        const $closeSchedPaletteModalBtn = $('#pp-schedule-palette-modal-close');
        const $cancelSaveColorsBtn = $('#pp-cancel-save-colors');
        const $saveBtn_colorPaletteModal = $('#pp-sched-palette-save-colors');
        const $schedColorPaletteForm = $('#pp-color-palette-form');

        // Open modal
        $scheduleColorPaletteBtn.on('click', function () {
            $scheduleColorPaletteModal.css('display', 'flex');
            const templateKey = $('#pp-template-selector').val();
            generateColorFields(templateKey);
            generateFontFields(templateKey);
        });

        // Close modal function
        function paletteModal() {
            $scheduleColorPaletteModal.css('display', 'none');
            $schedColorPaletteForm[0].reset();
            //reset the modal back to the saved values in the templates
            populateColorInputsFromTemplates();
            resetScheduleTemplateRendering();
            $('#pp-template-selector').val(ppScheduleTemplates.selected_template);
        }

        $closeSchedPaletteModalBtn.on('click', paletteModal);
        $cancelSaveColorsBtn.on('click', paletteModal);

        // Close modal when clicking outside
        $scheduleColorPaletteModal.on('click', function (e) {
            if (e.target === this) {
                paletteModal();
            }
        });

        // Form submission
        $saveBtn_colorPaletteModal.on('click', function () {
            // Check form validity
            if (!$schedColorPaletteForm[0].checkValidity()) {
                $schedColorPaletteForm[0].reportValidity();
                return;
            }

            const templateKey = $('#pp-template-selector').val();
            const colorSettings = getColorSettings();
            const fontSettings  = getFontSettings();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'puck_press_update_schedule_colors',
                    template_key: templateKey,
                    colors: colorSettings,
                    fonts:  fontSettings
                },
                success: function (response) {
                    if (response.success) {
                        // Show success message
                        console.log(response.data);
                        //update state of the color settings
                        ppScheduleTemplates.scheduleTemplates[templateKey] = colorSettings;
                        ppScheduleTemplates.fontSettings[templateKey]      = fontSettings;
                        ppScheduleTemplates.selected_template = templateKey;
                        generateColorFields(templateKey);
                        generateFontFields(templateKey);

                    } else {
                        // Show error message
                        console.log(response.data);
                    }
                },
                error: function () {
                    // Show error message
                    console.log(response.data);
                }
            });
        });


        // ── Color fields ─────────────────────────────────────────────────────

        function getColorSettings() {
            const colorSettings = {};

            $('#pp-dynamic-color-fields .pp-palette-form-group').each(function () {
                const label = $(this).find('.pp-form-label').text().trim().toLowerCase();
                const value = $(this).find('.pp-color-pallette-color-value').val().trim();

                // Use the label (in lowercase) as the key
                colorSettings[label] = value;
            });

            return colorSettings;
        }

        // Function to generate color fields based on template
        function generateColorFields(templateKey) {
            // Get schedule templates data from PHP
            const scheduleTemplates = ppScheduleTemplates.scheduleTemplates;
            const template = scheduleTemplates[templateKey];
            if (!template) return;

            // Clear existing color fields
            $('#pp-dynamic-color-fields').empty();
            // Generate color fields for each color in the template
            Object.entries(template).forEach(([colorKey, colorValue]) => {
                // Format the color key for display (capitalize first letter)
                const colorLabel = colorKey.charAt(0).toUpperCase() + colorKey.slice(1);

                // Create HTML for color input group
                const colorField = `
                <div class="pp-palette-form-group">
                    <label for="pp-${templateKey}-${colorKey}-color" class="pp-form-label">${colorLabel}</label>
                    <div class="pp-color-pallette-color-input-group">
                        <input type="color" class="pp-color-pallette-color-preview"
                               id="pp-${templateKey}-${colorKey}-color-picker-input" value="${colorValue}">
                        <input type="text" class="pp-color-pallette-color-value"
                               id="pp-${templateKey}-${colorKey}-color-text-input" value="${colorValue}">
                    </div>
                </div>
            `;

                // Add the color field to the form
                $('#pp-dynamic-color-fields').append(colorField);
            });

            // Re-attach event listeners for new inputs
            $('.pp-color-pallette-color-preview').off('input').on('input', function () {
                const colorValue = $(this).val();
                const textInputId = $(this).attr('id').replace('-picker-', '-text-');
                $('#' + textInputId).val(colorValue).trigger('input');
            });

            $('.pp-color-pallette-color-value').off('input').on('input', function () {
                const colorValue = $(this).val();
                const pickerInputId = $(this).attr('id').replace('-text-', '-picker-');
                $('#' + pickerInputId).val(colorValue);
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
            $('#pp-schedule-dynamic-font-fields .pp-font-form-group').each(function () {
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
            const fonts  = (ppScheduleTemplates.fontSettings && ppScheduleTemplates.fontSettings[templateKey]) || {};
            const labels = (ppScheduleTemplates.fontLabels   && ppScheduleTemplates.fontLabels[templateKey])   || {};
            const $container = $('#pp-schedule-dynamic-font-fields');

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
                    dropdownParent: $scheduleColorPaletteModal,
                    width: '100%',
                });

                // Pre-load font in admin so preview renders immediately on modal open
                if (fontValue) loadGoogleFont(fontValue);

                // Live preview via Select2's own event
                $select.on('select2:select', function (e) {
                    const fontName = (e.params.data.id || '').trim();
                    const cssValue = fontFamilyCss(fontName);
                    document.documentElement.style.setProperty(`--pp-${templateKey}-${fontKey}`, cssValue);
                    $(`.${templateKey}_schedule_container`).css('font-family', cssValue);
                    if (fontName) loadGoogleFont(fontName);
                });
            });
        }

        // Listen for template selection changes
        $('#pp-template-selector').on('change', function () {
            const selectedTemplate = $(this).val();
            generateColorFields(selectedTemplate);
            generateFontFields(selectedTemplate);
            for(let key in ppScheduleTemplates.scheduleTemplates) {
                $(`.${key}_schedule_container`).hide();
            }
            $(`.${selectedTemplate}_schedule_container`).show();
        });

        //############################################################//
        //                                                            //
        //        Connect CSS variables to values in the modal        //
        //                                                            //
        //############################################################//
        $.each(ppScheduleTemplates.scheduleTemplates, function(templateKey, colorSet) {
            $.each(colorSet, function(colorKey, defaultValue) {
                const inputId = `#pp-${templateKey}-${colorKey}-color-text-input`;
                const cssVar = `--pp-${templateKey}-${colorKey}`;

                // Set initial value (optional)
                const initialValue = $(inputId).val() || defaultValue;
                document.documentElement.style.setProperty(cssVar, initialValue);

                // Bind change listener
                $(document).on('input', inputId, function() {
                    const newColor = $(this).val();
                    document.documentElement.style.setProperty(cssVar, newColor);
                });
            });
        });

        // Initialize font CSS vars on page load so the admin preview reflects saved fonts
        $.each(ppScheduleTemplates.fontSettings || {}, function (templateKey, fontSet) {
            $.each(fontSet, function (fontKey, fontValue) {
                if (fontValue) {
                    const cssValue = fontFamilyCss(fontValue);
                    document.documentElement.style.setProperty(`--pp-${templateKey}-${fontKey}`, cssValue);
                    $(`.${templateKey}_schedule_container`).css('font-family', cssValue);
                    loadGoogleFont(fontValue);
                }
            });
        });

        //reset the color inputs to the values in the templates for use if the user wants to
        //reset the colors or exits the modal without saving
        function populateColorInputsFromTemplates() {
            $.each(ppScheduleTemplates.scheduleTemplates, function(templateKey, colorSet) {
                $.each(colorSet, function(colorKey, colorValue) {
                    const colorInputSelector = `#pp-${templateKey}-${colorKey}-color-picker-input`;
                    const textInputSelector = `#pp-${templateKey}-${colorKey}-color-text-input`;
                    const cssVar = `--pp-${templateKey}-${colorKey}`;

                    // Set both color and text input values
                    $(colorInputSelector).val(colorValue);
                    $(textInputSelector).val(colorValue.toUpperCase());
                    document.documentElement.style.setProperty(cssVar, colorValue);
                });
            });
        }
        function resetScheduleTemplateRendering() {
            for(let key in ppScheduleTemplates.scheduleTemplates) {
                $(`.${key}_schedule_container`).hide();
            }
            $(`.${ppScheduleTemplates.selected_template}_schedule_container`).show();
        }


    });
})(jQuery);
