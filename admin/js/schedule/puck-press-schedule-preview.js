(function ($) {
    jQuery(document).ready(function ($) {
        //init the preview state
        for(let key in ppScheduleTemplates.scheduleTemplates) {
            $(`.${key}_schedule_container`).hide();   
        }
        $(`.${ppScheduleTemplates.selected_template}_schedule_container`).show();
        
        for(let key in ppSliderTemplates.sliderTemplates) {
            $(`.${key}_slider_container`).hide();   
        }
        $(`.${ppSliderTemplates.selected_template}_slider_container`).show(); 

    });
})(jQuery);