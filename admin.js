var admin_refnotes = (function() {

    function Hash() {
        /* Copy-pasted from http://www.mojavelinux.com/articles/javascript_hashes.html */
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


    function NameHash(sentinel) {
        this.baseClass = Hash;
        this.baseClass('', sentinel);

        this.getItem = function(key) {
            return this.hasItem(key) ? this.items[key] : this.items[''];
        }
    }


    function List(id) {
        var list = $(id);

        function createOption(value, selected)
        {
            var option = document.createElement('option');

            option.text     = value;
            option.value    = value;
            option.sorting  = value.replace(/:/g, '-');
            option.selected = selected;

            return option;
        }

        function insertSorted(option) {
            var nextOption = null;

            for (var i = 0; i < list.options.length; i++) {
                if (list.options[i].sorting > option.sorting) {
                    nextOption = list.options[i];
                    break;
                }
            }

            if (nextOption != null) {
                list.insertBefore(option, nextOption);
            }
            else {
                list.appendChild(option);
            }
        }

        this.insertSorted = function() {
            switch (arguments.length) {
                case 1:
                    insertSorted(arguments[0]);
                    break;

                case 2:
                    insertSorted(createOption(arguments[0], arguments[1]));
                    break;
            }
        }

        this.update = function(values, selected) {
            list.options.length = 0;

            for (var value in values.items) {
                if (value != '') {
                    insertSorted(createOption(value, false));
                }
            }

            if (list.options.length > 0) {
                list.options[0].selected = true;
            }

            return this.getSelectedValue();
        }

        this.getSelectedValue = function() {
            return (list.selectedIndex != -1) ? list.options[list.selectedIndex].value : '';
        }

        this.removeValue = function(value) {
            var index = -1;

            for (var i = 0; i < list.options.length; i++) {
                if (list.options[i].value == value) {
                    index = i;
                    break;
                }
            }

            if (index != -1) {
                if (index < (list.options.length - 1)) {
                    list.selectedIndex = index + 1;
                }
                else {
                    list.selectedIndex = index - 1;
                }

                list.remove(index);
            }

            return this.getSelectedValue();
        }
    }


    var locale = (function() {
        var lang = new Hash();

        function initialize() {
            var element = $('refnotes-lang');
            if (element != null) {
                var strings = element.innerHTML.split(/:eos:\n/);

                for (var i = 0; i < strings.length; i++) {
                    var match = strings[i].match(/^\s*(\w+) : (.+)/);

                    if (match != null) {
                        lang.setItem(match[1], match[2]);
                    }
                }
            }
        }

        function getString(key) {
            var string = '';

            if (lang.hasItem(key)) {
                string = lang.getItem(key);

                if (arguments.length > 1) {
                    for (var i = 1; i < arguments.length; i++) {
                        var regexp = new RegExp('\\{' + i + '\\}');
                        string = string.replace(regexp, arguments[i]);
                    }
                }
            }

            return string;
        }

        return {
            initialize : initialize,
            getString  : getString
        }
    })();


    var server = (function() {
        var ajax = new sack(DOKU_BASE + '/lib/exe/ajax.php');
        var timer = null;
        var transaction = null;
        var onCompletion = null;

        ajax.encodeURIString = false;

        ajax.onLoading = function() {
            setStatus(transaction, 'info');
        }

        ajax.afterCompletion = function() {
            if (ajax.responseStatus[0] == '200') {
                onCompletion();
            }
            else {
                setStatus(transaction + '_failed', 'error');
            }

            transaction = null;
            onCompletion = null;
        }

        function onLoaded() {
            setStatus('loaded', 'success', 3000);

            var settings = JSON.parse(ajax.response);

            reloadSettings(settings);
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

                //TODO: serialize settings

                ajax.runAJAX();
            }
            else {
                setStatus('saving_failed', 'error');
            }
        }

        function setStatus(textId, styleId, timeout) {
            var status = $('server-status');
            status.className   = styleId;
            status.textContent = locale.getString(textId);

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
        }
    })();


    var namespaces = (function() {
        var list       = null;
        var fields     = new Array();
        var namespaces = new NameHash(new DefaultNamespace());
        var current    = namespaces.getItem('');
        var defaults   = new Hash(
            'refnote-id'           , 'numeric',
            'reference-base'       , 'super',
            'reference-font-weight', 'normal',
            'reference-font-style' , 'normal',
            'reference-format'     , 'right-parent',
            'notes-separator'      , '100%'
        );

        function DefaultNamespace() {
            this.isReadOnly = function() {
                return true;
            }

            this.getName = function() {
                return '';
            }

            this.setStyle = function(name, value) {
            }

            this.getStyle = function(name) {
                return defaults.getItem(name);
            }

            this.getStyleInheritance = function(name) {
                return 'default';
            }
        }

        function Namespace(name, data) {
            var style = new Hash();

            if (typeof(data) != 'undefined') {
                for (var s in data) {
                    style.setItem(s, data[s]);
                }
            }

            function getParent() {
                var parent = name.replace(/\w*:$/, '');

                while (!namespaces.hasItem(parent)) {
                    parent = parent.replace(/\w*:$/, '');
                }

                return namespaces.getItem(parent);
            }

            this.isReadOnly = function() {
                return false;
            }

             this.getName = function() {
                return name;
            }

            this.setStyle = function(name, value) {
                if (value == 'inherit') {
                    style.removeItem(name);
                }
                else {
                    style.setItem(name, value);
                }
            }

            this.getStyle = function(name) {
                var result;

                if (style.hasItem(name)) {
                    result = style.getItem(name);
                }
                else {
                    result = getParent().getStyle(name);
                }

                return result;
            }

            this.getStyleInheritance = function(name) {
                var result = '';

                if (!style.hasItem(name)) {
                    result = getParent().getStyleInheritance(name) || 'inherited';
                }

                return result;
            }
        }

        function Field(styleName) {
            this.element = $('field-' + styleName);

            this.updateInheretance = function(inheritance) {
                var cell = this.element.parentNode.parentNode;

                removeClass(cell, 'default');
                removeClass(cell, 'inherited');

                addClass(cell, current.getStyleInheritance(styleName));
            }

        }

        function SelectField(styleName) {
            this.baseClass = Field;
            this.baseClass(styleName);

            var combo = this.element;
            var self  = this;

            addEvent(combo, 'change', function() {
                self.onChange();
            });

            function setSelection(value) {
                for (var o = 0; o < combo.options.length; o++) {
                    if (combo.options[o].value == value) {
                        combo.options[o].selected = true;
                    }
                }
            }

            this.onChange = function() {
                var value = combo.options[combo.selectedIndex].value;

                current.setStyle(styleName, value);

                this.updateInheretance();

                if ((value == 'inherit') || current.isReadOnly()) {
                    setSelection(current.getStyle(styleName));
                }
            }

            this.update = function() {
                this.updateInheretance();
                setSelection(current.getStyle(styleName));
                combo.disabled = current.isReadOnly();
            }
        }

        function TextField(styleName, validate) {
            this.baseClass = Field;
            this.baseClass(styleName);

            var edit   = this.element;
            var button = $(this.element.id + '-inherit');
            var self   = this;

            addEvent(edit, 'change', function() {
                self.setValue(validate(edit.value));
            });

            addEvent(button, 'click', function() {
                self.setValue('inherit');
            });

            this.setValue = function(value) {
                current.setStyle(styleName, value);

                this.updateInheretance();

                if ((edit.value != value) || (value == 'inherit') || current.isReadOnly()) {
                    edit.value = current.getStyle(styleName);
                }
            }

            this.update = function() {
                this.updateInheretance();

                edit.value      = current.getStyle(styleName);
                edit.disabled   = current.isReadOnly();
                button.disabled = current.isReadOnly();
            }
        }

        function initialize() {
            fields.push(new SelectField('refnote-id'));
            fields.push(new SelectField('reference-base'));
            fields.push(new SelectField('reference-font-weight'));
            fields.push(new SelectField('reference-font-style'));
            fields.push(new SelectField('reference-format'));
            fields.push(new TextField('notes-separator', function(value){
                if (value.match(/(?:\d+\.?|\d*\.\d+)(?:%|em|px)|none/) == null) {
                    value = 'none';
                }
                return value;
            }));

            list = new List('select-namespaces');

            addEvent($('select-namespaces'), 'change', onNamespaceChange);
            addEvent($('add-namespaces'), 'click', onAddNamespace);
            addEvent($('delete-namespaces'), 'click', onDeleteNamespace);

            $('name-namespaces').disabled   = true;
            $('add-namespaces').disabled    = true;
            $('delete-namespaces').disabled = true;

            updateFields();
        }

        function onNamespaceChange(event) {
            setCurrent(list.getSelectedValue());
        }

        function onAddNamespace(event) {
            try {
                var name = validateName();

                namespaces.setItem(name, new Namespace(name));

                list.insertSorted(name, true);

                setCurrent(name);
            }
            catch (error) {
                alert(error);
            }
        }

        function onDeleteNamespace(event) {
            if (confirm(locale.getString('delete_ns', current.getName()))) {
                namespaces.removeItem(current.getName());

                setCurrent(list.removeValue(current.getName()));
            }
        }

        function validateName() {
            var names = $('name-namespaces').value.split(':');
            var name  = ':';

            for (var i = 0; i < names.length; i++) {
                if (names[i] != '') {
                    /* ECMA regexp doesn't support POSIX character classes, so [a-zA-Z] is used instead of [[:alpha:]] */
                    if (names[i].match(/^[a-zA-Z]\w*$/) == null) {
                        throw locale.getString('invalid_ns_name');
                    }

                    name += names[i] + ':';
                }
            }

            if ((name != '') && namespaces.hasItem(name)) {
                throw locale.getString('ns_name_exists', name);
            }

            return name;
        }

        function reload(settings) {
            namespaces = new NameHash(new DefaultNamespace());

            for (var name in settings) {
                if (name.match(/^:$|^:.+?:$/) != null) {
                    namespaces.setItem(name, new Namespace(name, settings[name]));
                }
            }

            $('name-namespaces').disabled = false;
            $('add-namespaces').disabled  = false;

            setCurrent(list.update(namespaces));
        }

        function setCurrent(name) {
            current = namespaces.getItem(name);

            updateFields();
        }

        function updateFields() {
            $('name-namespaces').value      = current.getName();
            $('delete-namespaces').disabled = current.isReadOnly();

            for (var i = 0; i < fields.length; i++) {
                fields[i].update();
            }
        }

        return {
            initialize : initialize,
            reload     : reload
        }
    })();


    function initialize() {
        locale.initialize();
        namespaces.initialize();

        server.loadSettings();
    }

    function reloadSettings(settings) {
        namespaces.reload(settings['namespaces']);
    }

    function addClass(element, className) {
        if (className != '') {
            var regexp = new RegExp('\\b' + className + '\\b');
            if (!element.className.match(regexp)) {
                element.className = (element.className + ' ' + className).replace(/^\s/, '');
            }
        }
    }

    function removeClass(element, className) {
        var regexp = new RegExp('\\b' + className + '\\b');
        element.className = element.className.replace(regexp, '').replace(/^\s|(\s)\s|\s$/g, '$1');
    }

    return {
        initialize : initialize
    }
})();


addInitEvent(function(){
    admin_refnotes.initialize();
});
