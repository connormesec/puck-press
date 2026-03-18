(function ($) {
    jQuery(document).ready(function ($) {
        //init the preview state
        const keys = Object.keys(ppRosterTemplates.rosterTemplates);
        console.log('[PP Roster Preview JS] ppRosterTemplates:', ppRosterTemplates);
        console.log('[PP Roster Preview JS] ppRosterAdmin:', typeof ppRosterAdmin !== 'undefined' ? ppRosterAdmin : 'UNDEFINED');
        console.log('[PP Roster Preview JS] template keys:', keys);
        for (let key of keys) {
            const $el = $(`.${key}_roster_container`);
            console.log(`[PP Roster Preview JS] hiding .${key}_roster_container — found ${$el.length} element(s)`);
            $el.hide();
        }
        const selected = ppRosterTemplates.selected_template || keys[0] || '';
        console.log('[PP Roster Preview JS] showing selected:', selected);
        if (selected) {
            const $sel = $(`.${selected}_roster_container`);
            console.log(`[PP Roster Preview JS] .${selected}_roster_container — found ${$sel.length} element(s)`);
            $sel.show();
        }
    });
})(jQuery);