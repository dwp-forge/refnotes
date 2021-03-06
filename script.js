(function () {
    let floater = null;
    let tracking = false;
    let timer = null;

    function createFloater() {
        return jQuery('<div id="insitu__fn" />')
            .addClass('insitu-footnote JSpopup')
            .css({ visibility : 'hidden', left : '0px', top : '0px' })
            .mouseleave(function () { jQuery(this).hide(); })
            .appendTo('.dokuwiki:first');
    }

    function getFloater() {
        if (!floater) {
            floater = jQuery('#insitu__fn');
            if (floater.length == 0) {
                floater = createFloater();
            }
        }

        return floater;
    }

    let preview = {
        setNoteId(id) {
            // locate the note span element
            let note = jQuery('#' + id.replace(/:/g, '\\:') + '\\:text');
            if (note.length == 0) {
                return false;
            }

            // remove any element ids from the content to ensure that they remain unique
            // and display hidden tooltip so we can move it around
            getFloater()
                .html(note.html().replace(/\bid\s*=\s*".*?"/gi, ''))
                .css('visibility', 'hidden')
                .show();

            return true;
        },

        show() {
            getFloater()
                .css('visibility', 'visible')
                .show();
        },

        hide() {
            // prevent creation of the floater and re-hiding it on window.scroll()
            if (floater && floater.is(':visible')) {
                floater.hide();
            }
        },

        move(event, dx, dy) {
            getFloater().position({
                my : 'left top',
                of : event,
                offset : dx + ' ' + dy,
                collision : 'flip'
            });
        }
    };

    function getNoteId(event) {
        return event.target.href.replace(/^.*?#([\w:]+)$/gi, '$1');
    }

    plugin_refnotes = {
        popup : {
            show(event) {
                plugin_refnotes.tooltip.hide(event);
                if (preview.setNoteId(getNoteId(event))) {
                    preview.move(event, 2, 2);
                    preview.show();
                }
            }
        },

        tooltip : {
            show(event) {
                plugin_refnotes.tooltip.hide(event);
                if (preview.setNoteId(getNoteId(event))) {
                    timer = setTimeout(function () { preview.show(); }, 500);
                    tracking = true;
                }
            },

            hide(event) {
                if (tracking) {
                    clearTimeout(timer);
                    tracking = false;
                }
                preview.hide();
            },

            track(event) {
                if (tracking) {
                    preview.move(event, 10, 12);
                }
            }
        }
    };
})();

jQuery(function () {
    jQuery('a.refnotes-ref.note-popup').mouseenter(plugin_refnotes.popup.show);
    jQuery('a.refnotes-ref.note-tooltip')
        .mouseenter(plugin_refnotes.tooltip.show)
        .mouseleave(plugin_refnotes.tooltip.hide);
    jQuery(document).mousemove(plugin_refnotes.tooltip.track);
    jQuery(window).scroll(plugin_refnotes.tooltip.hide);
});
