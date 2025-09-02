(function ($) {
    jQuery(document).ready(function ($) {
        //############################################################//
        //                                                            //
        //                Slider Color Palette functionality        //
        //                                                            //
        //############################################################//
        const $sliderColorPaletteModal = $('#pp-slider-paletteModal');
        const $sliderColorPaletteBtn = $('#pp-schedule-slider-colorPaletteBtn');
        const $closeSliderPaletteModalBtn = $('#pp-slider-palette-modal-close');
        const $cancelSaveColorsBtn = $('#pp-cancel-save-colors');
        const $saveBtn_colorPaletteModal = $('#pp-slider-palette-save-colors');
        const $sliderColorPaletteForm = $('#pp-slider-color-palette-form');

        // Open modal
        $sliderColorPaletteBtn.on('click', function () {
            $sliderColorPaletteModal.css('display', 'flex');
            generateColorFields($('#pp-slider-template-selector').val());
        });

        // Close modal function
        function paletteModal() {
            $sliderColorPaletteModal.css('display', 'none');
            $sliderColorPaletteForm[0].reset();
            //reset the modal back to the saved values in the templates
            populateColorInputsFromTemplates();
            resetScheduleTemplateRendering();
            $('#pp-slider-template-selector').val(ppSliderTemplates.selected_template);
        }

        $closeSliderPaletteModalBtn.on('click', paletteModal);
        $cancelSaveColorsBtn.on('click', paletteModal);

        // Close modal when clicking outside
        $sliderColorPaletteModal.on('click', function (e) {
            if (e.target === this) {
                paletteModal();
            }
        });

        // Form submission
        $saveBtn_colorPaletteModal.on('click', function () {
            // Check form validity
            if (!$sliderColorPaletteForm[0].checkValidity()) {
                $sliderColorPaletteForm[0].reportValidity();
                return;
            }

            function getColorSettings() {
                const colorSettings = {};

                $('#pp-slider-dynamic-color-fields .pp-palette-form-group').each(function () {
                    const label = $(this).find('.pp-form-label').text().trim().toLowerCase();
                    const value = $(this).find('.pp-color-pallette-color-value').val().trim();

                    // Use the label (in lowercase) as the key
                    colorSettings[label] = value;
                });

                return colorSettings;
            }

            const templateKey = $('#pp-slider-template-selector').val();
            const colorSettings = getColorSettings();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'puck_press_update_slider_colors',
                    template_key: templateKey,
                    colors: colorSettings
                },
                success: function (response) {
                    if (response.success) {
                        // Show success message
                        console.log(response.data);
                        //update state of the color settings
                        ppSliderTemplates.sliderTemplates[templateKey] = colorSettings;
                        ppSliderTemplates.slider_template = templateKey;
                        generateColorFields($('#pp-slider-template-selector').val());

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
            const sliderTemplates = ppSliderTemplates.sliderTemplates;
            const template = sliderTemplates[templateKey];
            if (!template) return;
            // Clear existing color fields
            $('#pp-slider-dynamic-color-fields').empty();
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
                $('#pp-slider-dynamic-color-fields').append(colorField);
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
        $('#pp-slider-template-selector').on('change', function () {
            const selectedTemplate = $(this).val();
            generateColorFields(selectedTemplate);
            for(let key in ppSliderTemplates.sliderTemplates) {
                $(`.${key}_slider_container`).hide();   
            }
            $(`.${selectedTemplate}_slider_container`).show(); 
        });

        //############################################################//
        //                                                            //
        //        Connect CSS variables to values in the modal        //
        //                                                            //
        //############################################################//
        $.each(ppSliderTemplates.sliderTemplates, function(templateKey, colorSet) {
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
            $.each(ppSliderTemplates.sliderTemplates, function(templateKey, colorSet) {
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
            for(let key in ppSliderTemplates.sliderTemplates) {
                $(`.${key}_slider_container`).hide();   
            }
            $(`.${ppSliderTemplates.selected_template}_slider_container`).show(); 
        }

        
    });
})(jQuery);