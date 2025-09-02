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
            generateColorFields($('#pp-template-selector').val());
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

            const templateKey = $('#pp-template-selector').val();
            const colorSettings = getColorSettings();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'puck_press_update_schedule_colors',
                    template_key: templateKey,
                    colors: colorSettings
                },
                success: function (response) {
                    if (response.success) {
                        // Show success message
                        console.log(response.data);
                        //update state of the color settings
                        ppScheduleTemplates.scheduleTemplates[templateKey] = colorSettings;
                        ppScheduleTemplates.selected_template = templateKey;
                        generateColorFields($('#pp-template-selector').val());

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

        // Listen for template selection changes
        $('#pp-template-selector').on('change', function () {
            const selectedTemplate = $(this).val();
            generateColorFields(selectedTemplate);
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