(function() {

    var namespaces = (function() {

        function DefaultNamespace() {
            this.getName = function() {
                return '';
            }

            this.getOptionHtml = function() {
                return '';
            }

            this.getStyle = function(name) {
                return settings.getItem(name);
            }

            this.getStyleInheritance = function(name) {
                return 2;
            }
        }

        function Namespace(namespaceName) {
            var style = new Hash();
            var name  = namespaceName;

            function getName() {
                return name;
            }

            function getOptionHtml() {
                return '<option value="' + name + '">' + name + '</option>';
            }

            function getParent() {
                var parent = name.replace(/\w*:$/, '');

                while (!namespaces.hasItem(parent)) {
                    parent = parent.replace(/\w*:$/, '');
                }

                return namespaces.getItem(parent);
            }

            function setStyle(name, value) {
                if (value == 'inherit') {
                    style.removeItem(name);
                }
                else {
                    style.setItem(name, value);
                }
            }

            function getStyle(name) {
                var result = null;

                if (style.hasItem(name)) {
                    result = style.getItem(name);
                }
                else {
                    result = getParent().getStyle(name);
                }

                return result;
            }

            function getStyleInheritance(name) {
                var result = 0;

                if (!style.hasItem(name)) {
                    result = (getParent().getStyleInheritance(name) == 2) ? 2 : 1;
                }

                return result;
            }

            function removeStyle(name) {
                style.removeItem(name);
            }

            this.getName             = getName;
            this.getOptionHtml       = getOptionHtml;
            this.setStyle            = setStyle;
            this.getStyle            = getStyle;
            this.getStyleInheritance = getStyleInheritance;
            this.removeStyle         = removeStyle;
        }

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

            current.setStyle(styleName, value);

            setInheretanceClass(combo, current.getStyleInheritance(styleName));

            if (value == 'inherit') {
                setComboSelection(combo, current.getStyle(styleName));
            }
        }

        function load() {
            //TODO: fetch data from server
            var namespace = new DefaultNamespace();

            namespaces.setItem(namespace.getName(), namespace);

            namespace = new Namespace(':');

            namespace.setStyle('reference-font-weight', 'bold');

            namespaces.setItem(namespace.getName(), namespace);

            namespace = new Namespace(':cite:');

            namespace.setStyle('refnote-id', 'latin-lower');

            namespaces.setItem(namespace.getName(), namespace);

            current = namespace;
        }

        function updateList() {
            var html = '';

            for (var namespaceName in namespaces.items) {
                html += namespaces.getItem(namespaceName).getOptionHtml();
            }

            $('select-namespaces').innerHTML = html;
        }

        function updateSetings() {
            for (var styleName in settings.items) {
                var combo = $('field-' + styleName);

                setInheretanceClass(combo, current.getStyleInheritance(styleName));
                setComboSelection(combo, current.getStyle(styleName));
            }
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
