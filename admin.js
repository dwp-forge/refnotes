(function() {

    var namespaces = (function() {
        var setting = new Hash(
            'refnote-id'           , 'numeric',
            'reference-base'       , 'super',
            'reference-font-weight', 'normal',
            'reference-font-style' , 'normal'
        );

        var namespaces = new Hash();
        var current = '';

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
            for (var styleName in setting.items) {
                var combo = $('field-' + styleName);

                if (!combo) {
                    continue;
                }

                var style = getStyleEx(current, styleName);

                setInheretanceClass(combo.parentNode.parentNode, style.inherited);
                setComboSelection(combo, style.value);
            }
        }

        function getStyleEx(namespaceName, styleName) {
            var style = {
                inherited : 0,
                value     : ''
            };

            style.value = getStyle(namespaceName, styleName);

            if (style.value == '') {
                style.inherited = 1;
                style.value = getStyle(getParentName(namespaceName), styleName, true);

                if (style.value == '') {
                    style.inherited = 2;
                    style.value = setting.getItem(styleName);
                }
            }

            return style;
        }

        function getStyle(namespaceName, styleName, recursive) {
            var result = '';

            if (namespaceName != '') {
                if (namespaces.hasItem(namespaceName)) {
                    var namespace = namespaces.getItem(namespaceName);

                    if (namespace.hasItem(styleName)) {
                        result = namespace.getItem(styleName);
                    }
                }

                if (recursive && (result == '')) {
                    result = getStyle(getParentName(namespaceName), styleName);
                }
            }

            return result;
        }

        function getParentName(namespaceName) {
            return namespaceName.replace(/\w*:$/, '');
        }

        function setInheretanceClass(cell, inherited) {
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

        return {
            load          : load,
            updateList    : updateList,
            updateSetings : updateSetings
        };
    })();



    admin_refnotes = {
        initialize: function() {
            if ($('general') != null) {
                namespaces.load();
                namespaces.updateList();
                namespaces.updateSetings();
            }
        }
    };




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
