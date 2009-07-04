(function() {

    var server = (function() {
        var ajax = new sack(DOKU_BASE + '/lib/exe/ajax.php');
        var timer = null;
        var transaction = null;
        var onCompletion = null;

        ajax.encodeURIString = false;

        ajax.onLoading = function() {
            $('field-note-text').value += 'Sending data...\n';
            setStatus(transaction, 'info');
        }

        ajax.onLoaded = function() {
            $('field-note-text').value += 'Data sent.\n';
        }

        ajax.onInteractive = function() {
            $('field-note-text').value += 'Getting data...\n';
        }

        ajax.afterCompletion = function() {
            printResponse();

            if (ajax.responseStatus[0] == '200') {
                onCompletion();
            }
            else {
                setStatus(transaction + '_failed', 'error');
            }

            transaction = null;
            onCompletion = null;
        }

        function printResponse() {
            var e = $('field-note-text');

            e.value += 'Completed.\n';
            e.value += 'URLString sent: ' + ajax.URLString + '\n';

            e.value += 'Status code: ' + ajax.responseStatus[0] + '\n';
            e.value += 'Status message: ' + ajax.responseStatus[1] + '\n';

            e.value += 'Response: "' + ajax.response + '"\n';
        }

        function onLoaded() {
            setStatus('loaded', 'success', 3000);
        }

        function onSaved() {
            setStatus('saved', 'success', 10000);
        }

        function loadSettings() {
            if (!ajax.failed && (transaction == null)) {
                transaction = 'loading';
                onCompletion = onLoaded;

                ajax.setVar('call', 'refnotes-admin');
                ajax.setVar('action', 'load-settings');
                ajax.runAJAX();
            }
            else {
                setStatus('loading_failed', 'error');
            }
        }

        function saveSettings(settings) {
            if (!ajax.failed && (transaction == null)) {
                transaction = 'saving';
                onCompletion = onSaved;

                ajax.setVar('call', 'refnotes-admin');
                ajax.setVar('action', 'save-settings');
                ajax.runAJAX();
            }
            else {
                setStatus('saving_failed', 'error');
            }
        }

        function setStatus(textId, styleId, timeout) {
            var status = $('server-status');
            status.className   = styleId;
            status.textContent = getLang(textId);

            if (typeof(timeout) != 'undefined') {
                timer = window.setTimeout(clearStatus, timeout);
            }
        }

        function clearStatus() {
            setStatus('status', 'cleared');
        }

        return {
            loadSettings : loadSettings,
            saveSettings : saveSettings
        };
    })();


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
            'reference-font-style' , 'normal',
            'reference-format'     , 'right-parent'
        );

        var namespaces = new Hash();
        var current = null;

        function initialize() {
            addEvent($('select-namespaces'), 'change', onNamespaceChange);
            for (var styleName in settings.items) {
                addEvent($('field-' + styleName), 'change', onSettingChange);
            }
        }

        function onNamespaceChange(event) {
            var list = event.target;

            current = namespaces.getItem(list.options[list.selectedIndex].value);

            updateSetings();
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

            var list = $('select-namespaces');

            list.innerHTML     = html;
            list.selectedIndex = 1;
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
            loadLanguageStrings();
            if ($('general') != null) {
                namespaces.initialize();
                namespaces.load();
                namespaces.updateList();
                namespaces.updateSetings();
            }
        }
    };

    function loadLanguageStrings() {
        var element = $('refnotes-lang');
        if (element != null) {
            if (typeof(LANG.plugins.refnotes) == 'undefined') {
                LANG.plugins.refnotes = {};
            }

            var strings = element.innerHTML.split(/:eos:\n/);

            for (var i = 0; i < strings.length; i++) {
                var match = strings[i].match(/^\s*(\w+) : (.+)/);

                if (match != null) {
                    LANG.plugins.refnotes[match[1]] = match[2];
                }
            }
        }
    }

    function getLang(key) {
        return LANG.plugins.refnotes[key];
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
