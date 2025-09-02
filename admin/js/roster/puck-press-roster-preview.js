(function ($) {
    jQuery(document).ready(function ($) {
        //init the preview state
        for(let key in ppRosterTemplates.rosterTemplates) {
            $(`.${key}_roster_container`).hide();   
        }
        $(`.${ppRosterTemplates.selected_template}_roster_container`).show();
        console.log(ppRosterTemplates)

    });
})(jQuery);