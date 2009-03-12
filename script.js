/**
 * Display a popup
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Chris Smith <chris@jalakai.co.uk>
 * @author Mykola Ostrovskyy <spambox03@mail.ru>
 */
function plugin_refnotes_popup(e) {
    var obj = e.target;
    var id = obj.href.replace(/^.*?#([\w:]+)$/gi, '$1');

    // get or create the footnote popup div
    var popup = $('refnotes-popup');
    if (!popup) {
        popup = document.createElement('div');
        popup.id = 'refnotes-popup';
        popup.className = 'insitu-footnote JSpopup dokuwiki';

        // autoclose on mouseout - ignoring bubbled up events
        addEvent(popup, 'mouseout', function(e) {
            if (e.target != popup) {
                e.stopPropagation();
                return;
            }
            // check if the element was really left
            if (e.pageX) {
                // Mozilla
                var bx1 = findPosX(popup);
                var bx2 = bx1 + popup.offsetWidth;
                var by1 = findPosY(popup);
                var by2 = by1 + popup.offsetHeight;
                var x = e.pageX;
                var y = e.pageY;
                if ((x > bx1) && (x < bx2) && (y > by1) && (y < by2)) {
                    // we're still inside boundaries
                    e.stopPropagation();
                    return;
                }
            }
            else {
                // IE
                if ((e.offsetX > 0) && (e.offsetX < (popup.offsetWidth - 1)) &&
                    (e.offsetY > 0) && (e.offsetY < (popup.offsetHeight - 1))){
                    // we're still inside boundaries
                    e.stopPropagation();
                    return;
                }
            }
            // okay, hide it
            popup.style.display = 'none';
        });
        document.body.appendChild(popup);
    }

    // locate the footnote anchor element
    var note = $( id );
    if (!note) {
        return;
    }

    // anchor parent is the footnote container, get its innerHTML
    var content = new String(note.innerHTML);

    // prefix ids on any elements with "insitu__" to ensure they remain unique
    content = content.replace(/\bid=\"(.*?)\"/gi, 'id="refnotes-popup-$1');

    // now put the content into the wrapper
    popup.innerHTML = content;

    // position the div and make it visible
    var x;
    var y;
    if (e.pageX) {
        // Mozilla
        x = e.pageX;
        y = e.pageY;
    }
    else {
        // IE
        x = e.offsetX;
        y = e.offsetY;
    }
    popup.style.position = 'absolute';
    popup.style.left = (x + 2) + 'px';
    popup.style.top = (y + 2) + 'px';
    popup.style.display = '';
}

/**
 * Add the event handlers to footnotes
 */
addInitEvent(function(){
    var elems = getElementsByClass('refnotes-ref-popup', null, 'a');
    for (var i = 0; i < elems.length; i++) {
        addEvent(elems[i], 'mouseover', function(e) {
            plugin_refnotes_popup(e);
        });
    }
});
