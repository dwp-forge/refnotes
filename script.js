(function() {
    var cssComp = document.compatMode && (document.compatMode == "CSS1Compat");
    var canvas = document.getElementsByTagName(cssComp ? "html" : "body")[0];
    var preview = null;
    var tracking = false;
    var shown = false;
    var timer = null;

    function createPreview() {
        preview = document.createElement('div');
        preview.id = 'insitu__fn';
        preview.className = 'insitu-footnote JSpopup dokuwiki';
        preview.style.position = 'absolute';
        preview.style.left = '0px';
        preview.style.top = '-100px';

        // autoclose on mouseout - ignoring bubbled up events
        addEvent(preview, 'mouseout', function(e) {
            if (e.target != preview) {
                e.stopPropagation();
                return;
            }
            // check if the element was really left
            var offsetX = e.pageX ? e.pageX - findPosX(preview) : e.offsetX;
            var offsetY = e.pageY ? e.pageY - findPosY(preview) : e.offsetY;
            var msieDelta = e.pageX ? 0 : 1;
            var width = preview.offsetWidth - msieDelta;
            var height = preview.offsetHeight - msieDelta;
            if ((offsetX > 0) && (offsetX < width) && (offsetY > 0) && (offsetY < height)) {
                // we're still inside boundaries
                e.stopPropagation();
                return;
            }
            hidePreview();
        });
        document.body.appendChild(preview);
    }

    function getPreview() {
        if (!preview) {
            preview = $('insitu__fn');
            if (!preview) {
                createPreview();
            }
        }
        return preview;
    }

    function showPreview() {
        var preview = getPreview();
        if (preview) {
            preview.style.visibility = 'visible';
            preview.style.display = '';
        }
        shown = true;
    }

    function hidePreview() {
        if (shown) {
            var preview = getPreview();
            if (preview) {
                preview.style.display = 'none';
            }
            shown = false;
        }
    }

    function movePreview(x, y, dx, dy) {
        var preview = getPreview();
        if (!preview) {
            return;
        }
        var windowWidth = canvas.clientWidth + canvas.scrollLeft;
        var windowHeight = canvas.clientHeight + canvas.scrollTop;
        var previewWidth = 10 + window.event ? preview.clientWidth : preview.offsetWidth;
        var previewHeight = 10 + window.event ? preview.clientHeight : preview.offsetHeight;

        x += dx;
        if ((x + previewWidth) > windowWidth) {
            x -= dx + 2 + previewWidth;
        }
        y += dy;
        if ((y + previewHeight) > windowHeight ) {
            y -= dy + 2 + previewHeight;
        }
        preview.style.left = x + 'px';
        preview.style.top = y + 'px';
    }

    function getNote(e) {
        var id = e.target.href.replace(/^.*?#([\w:]+)$/gi, '$1');
        // locate the note span element
        var note = $(id + ':text');
        if (!note) {
            return;
        }
        // get the note HTML
        var html = new String(note.innerHTML);
        // prefix ids on any elements to ensure they remain unique
        return html.replace(/\bid=\"(.*?)\"/gi, 'id="refnotes-preview-$1');
    }

    function log(message) {
        var console = window['console'];
        if (console && console.log) {
          console.log(message);
        }
    }

    plugin_refnotes = {
        popup: {
            show: function(e) {
                plugin_refnotes.tooltip.hide(e);

                var preview = getPreview();
                var note = getNote(e);
                if (!preview || !note) {
                    return;
                }
                // now put the content into the wrapper
                preview.innerHTML = note;

                // display hidden tooltip so we can measure it's size
                preview.style.visibility = 'hidden';
                preview.style.display = '';
                preview.style.left = '0px';
                preview.style.top = '0px';

                // position the div and make it visible
                var x = e.pageX ? e.pageX : e.offsetX;
                var y = e.pageY ? e.pageY : e.offsetY;
                movePreview(x, y, 2, 2);
                showPreview();
            }
        },

        tooltip: {
            show: function(e) {
                plugin_refnotes.tooltip.hide(e);

                var preview = getPreview();
                var note = getNote(e);
                if (!preview || !note) {
                    return;
                }
                // now put the content into the wrapper
                preview.innerHTML = note;

                // display hidden tooltip so we can measure it's size
                preview.style.visibility = 'hidden';
                preview.style.display = '';
                preview.style.left = '0px';
                preview.style.top = '0px';

                // start tooltip timeout
                timer = setTimeout(function(){ showPreview(); }, 500);
                tracking = true;
            },

            hide: function(e) {
                if (tracking) {
                    clearTimeout(timer);
                    tracking = false;
                }
                hidePreview();
            },

            track: function(e) {
                if (tracking) {
                    var x = e.pageX ? e.pageX : e.offsetX;
                    var y = e.pageY ? e.pageY : e.offsetY;
                    //var x = window.event ? event.clientX + canvas.scrollLeft : e.pageX;
                    //var y = window.event ? event.clientY + canvas.scrollTop : e.pageY;

                    movePreview(x, y, 10, 12);
                }
            }
        }
    };
})();

/**
 * Add the event handlers to footnotes
 */
addInitEvent(function(){
    var elems = getElementsByClass('refnotes-ref note-popup', null, 'a');
    for (var i = 0; i < elems.length; i++) {
        addEvent(elems[i], 'mouseover', function(e) { plugin_refnotes.popup.show(e); });
    }
    elems = getElementsByClass('refnotes-ref note-tooltip', null, 'a');
    for (var i = 0; i < elems.length; i++) {
        addEvent(elems[i], 'mouseover', function(e) { plugin_refnotes.tooltip.show(e); });
        addEvent(elems[i], 'mouseout', function(e) { plugin_refnotes.tooltip.hide(e); });
    }
    addEvent(document, 'mousemove', function(e) { plugin_refnotes.tooltip.track(e); });
    addEvent(window, 'scroll', function(e) { plugin_refnotes.tooltip.hide(e); });
});
