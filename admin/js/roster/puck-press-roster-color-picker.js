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
            generateColorFields($('#pp-roster-template-selector').val());
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

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'puck_press_update_roster_colors',
                    template_key: templateKey,
                    colors: colorSettings
                },
                success: function (response) {
                    if (response.success) {
                        console.log(response.data);
                        ppRosterTemplates.rosterTemplates[templateKey] = colorSettings;
                        ppRosterTemplates.selected_template = templateKey;
                        generateColorFields(templateKey);
                    } else {
                        console.log(response.data);
                    }
                },
                error: function () {
                    console.log('AJAX error');
                }
            });
        });

        function getColorSettings() {
            const settings = {};
            $('#pp-roster-dynamic-color-fields .pp-palette-form-group').each(function () {
                const label = $(this).find('.pp-form-label').text().trim().toLowerCase();
                const value = $(this).find('.pp-color-pallette-color-value').val().trim();
                settings[label] = value;
            });
            return settings;
        }

        function generateColorFields(templateKey) {
            const template = ppRosterTemplates.rosterTemplates[templateKey];
            if (!template) return;
            $('#pp-roster-dynamic-color-fields').empty();
            Object.entries(template).forEach(([colorKey, colorValue]) => {
                const colorLabel = colorKey.charAt(0).toUpperCase() + colorKey.slice(1);
                const field = `
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

        $('#pp-roster-template-selector').on('change', function () {
            const selected = $(this).val();
            generateColorFields(selected);
            for (let key in ppRosterTemplates.rosterTemplates) {
                $(`.${key}_roster_container`).hide();
            }
            $(`.${selected}_roster_container`).show();
        });

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
