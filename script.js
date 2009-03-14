(function() {
    var cssComp = document.compatMode && (document.compatMode == "CSS1Compat");
    var canvas = document.getElementsByTagName(cssComp ? "html" : "body")[0];
    var floater = null;
    var floaterWidth = 0;
    var floaterHeight = 0;
    var tracking = false;
    var shown = false;
    var timer = null;

    var preview = {
        createFloater: function() {
            floater = document.createElement('div');
            floater.id = 'insitu__fn';
            floater.className = 'insitu-footnote JSpopup dokuwiki';
            floater.style.position = 'absolute';
            floater.style.left = '0px';
            floater.style.top = '-100px';

            // autoclose on mouseout - ignoring bubbled up events
            addEvent(floater, 'mouseout', function(e) {
                if (e.target != floater) {
                    e.stopPropagation();
                    return;
                }
                // check if the element was really left
                var offsetX = e.pageX ? e.pageX - findPosX(floater) : e.offsetX;
                var offsetY = e.pageY ? e.pageY - findPosY(floater) : e.offsetY;
                var msieDelta = e.pageX ? 0 : 1;
                var width = floater.offsetWidth - msieDelta;
                var height = floater.offsetHeight - msieDelta;
                if ((offsetX > 0) && (offsetX < width) && (offsetY > 0) && (offsetY < height)) {
                    // we're still inside boundaries
                    e.stopPropagation();
                    return;
                }
                preview.hide();
            });
            document.body.appendChild(floater);
        },

        getFloater: function() {
            if (!floater) {
                floater = $('insitu__fn');
                if (!floater) {
                    this.createFloater();
                }
            }
            return floater;
        },

        measureFloater: function() {
            floaterWidth = 0;
            floaterHeight = 0;
            var floater = this.getFloater();
            if (floater) {
                var width = window.event ? floater.clientWidth : floater.offsetWidth;
                var height = window.event ? floater.clientHeight : floater.offsetHeight;
                if (width && height) {
                    // add CSS padding
                    floaterWidth = 10 + width;
                    floaterHeight = 10 + height;
                }
            }
        },

        setNoteId: function(id) {
            var floater = this.getFloater();
            // locate the note span element
            var note = $(id + ':text');
            if (!floater || !note) {
                return false;
            }
            // get the note HTML
            var html = new String(note.innerHTML);
            // prefix ids on any elements to ensure they remain unique
            html.replace(/\bid=\"(.*?)\"/gi, 'id="refnotes-preview-$1');

            // now put the content into the floater
            floater.innerHTML = html;

            // display hidden tooltip so we can measure it's size
            floater.style.visibility = 'hidden';
            floater.style.display = '';
            floater.style.left = '0px';
            floater.style.top = '0px';

            this.measureFloater();
            if (floaterWidth && ((floaterWidth / canvas.clientWidth) > 0.45)) {
                // simulate max-width in IE
                floater.style.width = '40%';
                this.measureFloater();
            }

            return true;
        },

        show: function() {
            var floater = this.getFloater();
            if (floater) {
                floater.style.visibility = 'visible';
                floater.style.display = '';
            }
            shown = true;
        },

        hide: function() {
            var floater = this.getFloater();
            if (floater) {
                floater.style.display = 'none';
                floater.style.width = '';
            }
            floaterWidth = 0;
            floaterHeight = 0;
            shown = false;
        },

        move: function(x, y, dx, dy) {
            var floater = this.getFloater();
            if (!floater) {
                return;
            }
            var windowWidth = canvas.clientWidth + canvas.scrollLeft;
            var windowHeight = canvas.clientHeight + canvas.scrollTop;
            if (!floaterWidth || !floaterHeight) {
                this.measureFloater();
            }
            x += dx;
            if ((x + floaterWidth) > windowWidth) {
                x -= dx + 2 + floaterWidth;
            }
            y += dy;
            if ((y + floaterHeight) > windowHeight ) {
                y -= dy + 2 + floaterHeight;
            }
            floater.style.left = x + 'px';
            floater.style.top = y + 'px';
        }
    };

    function getNoteId(e) {
        return e.target.href.replace(/^.*?#([\w:]+)$/gi, '$1');
    }

    function getEventX(e) {
        return e.pageX ? e.pageX : e.offsetX;
    }

    function getEventY(e) {
        return e.pageY ? e.pageY : e.offsetY;
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
                if (!preview.setNoteId(getNoteId(e))) {
                    return;
                }
                // position the floater and make it visible
                preview.move(getEventX(e), getEventY(e), 2, 2);
                preview.show();
            }
        },

        tooltip: {
            show: function(e) {
                plugin_refnotes.tooltip.hide(e);
                if (!preview.setNoteId(getNoteId(e))) {
                    return;
                }
                // start tooltip timeout
                timer = setTimeout(function(){ preview.show(); }, 500);
                tracking = true;
            },

            hide: function(e) {
                if (tracking) {
                    clearTimeout(timer);
                    tracking = false;
                }
                preview.hide();
            },

            track: function(e) {
                if (tracking) {
                    preview.move(getEventX(e), getEventY(e), 10, 12);
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
