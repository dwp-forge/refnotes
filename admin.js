(function() {

    var namespaces = (function() {
        var settings = new Hash(
            'refnote-id'           , 'numeric',
            'reference-base'       , 'super',
            'reference-font-weight', 'normal',
            'reference-font-style' , 'normal'
        );

        var namespaces = new Hash();
        var current = '';

        function initialize() {
            for (var styleName in settings.items) {
                addEvent($('field-' + styleName), 'change', onSettingChange);
            }
        }

        function onSettingChange(event) {
            var combo = event.target;
            var styleName = combo.id.replace(/^field-/, '');
            var value = combo.options[combo.selectedIndex].value;
            var namespace = namespaces.getItem(current);

            if (value == 'inherit') {
                namespace.removeItem(styleName);
            }
            else {
                namespace.setItem(styleName, value);
            }

            var style = getStyleEx(current, styleName);

            setInheretanceClass(combo, style.inherited);

            if (value == 'inherit') {
                setComboSelection(combo, style.value);
            }
        }

        function load() {
            //TODO: fetch data from server
            var namespace = new Hash();

            namespace.setItem('reference-font-weight', 'bold');

            namespaces.setItem(':', namespace);

            namespace = new Hash();

            namespace.setItem('refnote-id', 'latin-lower');

            namespaces.setItem(':cite:', namespace);

            current = ':cite:';
        }

        function updateList() {
            var html = '';

            for (var namespaceName in namespaces.items) {
                html += '<option value="' + namespaceName + '">' + namespaceName + '</option>';
            }

            $('select-namespaces').innerHTML = html;
        }

        function updateSetings() {
            for (var styleName in settings.items) {
                var combo = $('field-' + styleName);
                var style = getStyleEx(current, styleName);

                setInheretanceClass(combo, style.inherited);
                setComboSelection(combo, style.value);
            }
        }

        function getStyleEx(namespaceName, styleName) {
            var style = {
                inherited : 0,
                value     : null
            };

            style.value = getStyle(namespaceName, styleName);

            if (style.value == null) {
                style.inherited = 1;
                style.value = getStyle(getParentName(namespaceName), styleName, true);

                if (style.value == null) {
                    style.inherited = 2;
                    style.value = settings.getItem(styleName);
                }
            }

            return style;
        }

        function getStyle(namespaceName, styleName, recursive) {
            var result = null;

            if (namespaceName != null) {
                if (namespaces.hasItem(namespaceName)) {
                    var namespace = namespaces.getItem(namespaceName);

                    if (namespace.hasItem(styleName)) {
                        result = namespace.getItem(styleName);
                    }
                }

                if (recursive && (result == null)) {
                    result = getStyle(getParentName(namespaceName), styleName);
                }
            }

            return result;
        }

        function getParentName(namespaceName) {
            return namespaceName.replace(/\w*:$/, '');
        }

        function setInheretanceClass(combo, inherited) {
            var cell = combo.parentNode.parentNode;

            removeClass(cell, 'default');
            removeClass(cell, 'inherited');

            switch (inherited) {
                case 2:
                    addClass(cell, 'default');
                    break;
                case 1:
                    addClass(cell, 'inherited');
                    break;
            }
        }

        function setComboSelection(combo, value) {
            for (var o = 0; o < combo.options.length; o++) {
                if (combo.options[o].value == value) {
                     combo.options[o].selected = true;
                }
            }
        }

        return {
            initialize    : initialize,
            load          : load,
            updateList    : updateList,
            updateSetings : updateSetings
        };
    })();



    admin_refnotes = {
        initialize: function() {
            if ($('general') != null) {
                namespaces.initialize();
                namespaces.load();
                namespaces.updateList();
                namespaces.updateSetings();
            }
        }
    };



    function addClass(element, className) {
        var regexp = new RegExp('\\b' + className + '\\b', '');
        if (!element.className.match(regexp)) {
            element.className = (element.className + ' ' + className).replace(/^\s$/, '');
        }
    }

    function removeClass(element, className) {
        var regexp = new RegExp('\\b' + className + '\\b', '');
        element.className = element.className.replace(regexp, '').replace(/^\s|(\s)\s|\s$/g, '$1');
    }




    function Hash() {
        // copy-pasted from http://www.mojavelinux.com/articles/javascript_hashes.html
        this.length = 0;
        this.items = new Array();

        for (var i = 0; i < arguments.length; i += 2) {
            if (typeof(arguments[i + 1]) != 'undefined') {
                this.items[arguments[i]] = arguments[i + 1];
                this.length++;
            }
        }

        this.removeItem = function(key) {
            if (typeof(this.items[key]) != 'undefined') {
                this.length--;
                delete this.items[key];
            }
        }

        this.getItem = function(key) {
            return this.items[key];
        }

        this.setItem = function(key, value) {
            if (typeof(value) != 'undefined') {
                if (typeof(this.items[key]) == 'undefined') {
                    this.length++;
                }
                this.items[key] = value;
            }
        }

        this.hasItem = function(key) {
            return typeof(this.items[key]) != 'undefined';
        }
    }













})();


addInitEvent(function(){
    admin_refnotes.initialize();
});
