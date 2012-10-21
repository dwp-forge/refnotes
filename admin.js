var refnotes_admin = (function () {
    var modified = false;

    function Hash() {
        /* Copy-pasted from http://www.mojavelinux.com/articles/javascript_hashes.html */
        this.length = 0;
        this.items = [];

        if (arguments.length == 1) {
            if (typeof(arguments[0]) != 'undefined') {
                for (var key in arguments[0]) {
                    this.items[key] = arguments[0][key];
                    this.length++;
                }
            }
        }
        else if (arguments.length > 1) {
            for (var i = 0; i < arguments.length; i += 2) {
                if (typeof(arguments[i + 1]) != 'undefined') {
                    this.items[arguments[i]] = arguments[i + 1];
                    this.length++;
                }
            }
        }

        this.removeItem = function (key) {
            if (typeof(this.items[key]) != 'undefined') {
                this.length--;
                delete this.items[key];
            }
        }

        this.getItem = function (key) {
            return this.items[key];
        }

        this.setItem = function (key, value) {
            if (typeof(value) != 'undefined') {
                if (typeof(this.items[key]) == 'undefined') {
                    this.length++;
                }

                this.items[key] = value;
            }
        }

        this.hasItem = function (key) {
            return typeof(this.items[key]) != 'undefined';
        }
    }


    function NameHash(sentinel) {
        this.baseClass = Hash;
        this.baseClass();

        this.base = {
            getItem : this.getItem,
            hasItem : this.hasItem
        };

        this.getItem = function (key) {
            return (key == '') ? sentinel : this.base.getItem.apply(this, arguments);
        }

        this.hasItem = function (key) {
            return (key == '') ? true : this.base.hasItem.apply(this, arguments);
        }

        this.clear = function () {
            this.length = 0;
            this.items = [];
        }
    }


    function NamedObjectHash(sentinel) {
        this.baseClass = Hash;
        this.baseClass();

        this.addItem = function (value) {
            this.setItem(value.getName(), value);
        }
    }


    function List(id) {
        var list = jQuery(id);

        function appendOption(value) {
            jQuery('<option>')
                .html(value)
                .val(value)
                .prop('sorting', value.replace(/:/g, '-').replace(/(-\w+)$/, '-$1'))
                .appendTo(list);
        }

        function sortOptions() {
            list.append(list.children().get().sort(function (a, b) {
                return a.sorting > b.sorting ? 1 : -1;
            }));
        }

        this.getSelectedValue = function () {
            return list.val();
        }

        this.insertValue = function (value) {
            appendOption(value);
            sortOptions();

            return list.children('[value="' + value + '"]').attr('selected', 'selected').val();
        }

        this.reload = function (values) {
            list.empty();

            for (var value in values.items) {
                if (value != '') {
                    appendOption(value);
                }
            }

            sortOptions();

            return list.children(':first').attr('selected', 'selected').val();
        }

        this.removeValue = function (value) {
            var option = list.children('[value="' + value + '"]');

            if (option.length == 1) {
                list.prop('selectedIndex', option.index() + (option.is(':last-child') ? -1 : 1));
                option.remove();
            }

            return list.val();
        }

        this.renameValue = function (oldValue, newValue) {
            if (list.children('[value="' + oldValue + '"]').remove().length == 1) {
                this.insertValue(newValue);
            }

            return list.val();
        }
    }


    var locale = (function () {
        var lang = new Hash();

        function initialize() {
            jQuery.each(jQuery('#refnotes-lang').html().split(/:eos:/), function (key, value) {
                var match = value.match(/^\s*(\w+) : (.+)/);
                if (match != null) {
                    lang.setItem(match[1], match[2]);
                }
            });
        }

        function getString(key) {
            var string = lang.hasItem(key) ? lang.getItem(key) : '';

            if ((string.length > 0) && (arguments.length > 1)) {
                for (var i = 1; i < arguments.length; i++) {
                    string = string.replace(new RegExp('\\{' + i + '\\}'), arguments[i]);
                }
            }

            return string;
        }

        return {
            initialize : initialize,
            getString  : getString
        }
    })();


    var server = (function () {
        var timer = null;
        var transaction = null;

        function sendRequest(request, data, success) {
            if (transaction == null) {
                transaction = request;

                jQuery.ajax({
                    cache   : false,
                    data    : data,
                    global  : false,
                    success : success,
                    type    : 'POST',
                    timeout : 10000,
                    url     : DOKU_BASE + 'lib/exe/ajax.php',
                    beforeSend : function () {
                        setStatus('info', transaction);
                    },
                    error : function (xhr, status, message) {
                        setErrorStatus((status == 'parseerror') ? 'invalid_data' : transaction + '_failed', message);
                    },
                    dataFilter : function (data) {
                        var cookie = '{B27067E9-3DDA-4E31-9768-E66F23D18F4A}';
                        var match = data.match(new RegExp(cookie + '(.+?)' + cookie));

                        if ((match == null) || (match.length != 2)) {
                            throw 'Malformed response';
                        }

                        return match[1];
                    },
                    complete : function () {
                        transaction = null;
                    }
                });
            }
            else {
                setErrorStatus(request + '_failed', 'Server is busy');
            }
        }

        function loadSettings() {
            sendRequest('loading', {
                call   : 'refnotes-admin',
                action : 'load-settings'
            }, function (data) {
                setSuccessStatus('loaded', 3000);
                reloadSettings(data);
            });
        }

        function saveSettings(settings) {
            sendRequest('saving', {
                call     : 'refnotes-admin',
                action   : 'save-settings',
                settings : JSON.stringify(settings)
            }, function (data) {
                if (data == 'saved') {
                    modified = false;

                    setSuccessStatus('saved', 10000);
                }
                else {
                    setErrorStatus('saving_failed', 'Server FS access error');
                }
            });
        }

        function setStatus(status, message) {
            window.clearTimeout(timer);

            if (message.match(/^\w+$/) != null) {
                message = locale.getString(message);
            }

            jQuery('#server-status')
                .removeClass()
                .addClass(status)
                .text(message);
        }

        function setErrorStatus(messageId, details) {
            setStatus('error', locale.getString(messageId, details));
        }

        function setSuccessStatus(messageId, timeout) {
            setStatus('success', messageId);

            timer = window.setTimeout(function () {
                setStatus('cleared', 'status');
            }, timeout);
        }

        return {
            loadSettings : loadSettings,
            saveSettings : saveSettings
        }
    })();


    var general = (function () {
        var fields   = new NamedObjectHash();
        var defaults = new Hash(
            'replace-footnotes', false,
            'reference-db-enable', false,
            'reference-db-namespace', ':refnotes:'
        );

        function Field(settingName) {
            this.element = jQuery('#field-' + settingName);

            this.element.change(this, function (event) {
                event.data.updateDefault();
                modified = true;
            });

            this.getName = function () {
                return settingName;
            }

            this.updateDefault = function () {
                this.element.parents('td').toggleClass('default', this.getValue() == defaults.getItem(settingName));
            }

            this.enable = function (enable) {
                this.element.prop('disabled', !enable);
            }
        }

        function CheckField(settingName) {
            this.baseClass = Field;
            this.baseClass(settingName);

            this.setValue = function (value) {
                this.element.attr('checked', value);
                this.updateDefault();
            }

            this.getValue = function () {
                return this.element.is(':checked');
            }

            this.setValue(defaults.getItem(settingName));
            this.enable(false);
        }

        function TextField(settingName) {
            this.baseClass = Field;
            this.baseClass(settingName);

            this.setValue = function (value) {
                this.element.val(value);
                this.updateDefault();
            }

            this.getValue = function () {
                return this.element.val();
            }

            this.setValue(defaults.getItem(settingName));
            this.enable(false);
        }

        function initialize() {
            fields.addItem(new CheckField('replace-footnotes'));
            fields.addItem(new CheckField('reference-db-enable'));
            fields.addItem(new TextField('reference-db-namespace'));

            jQuery('#field-reference-db-namespace').css('width', '19em');
        }

        function reload(settings) {
            for (var name in settings) {
                if (fields.hasItem(name)) {
                    fields.getItem(name).setValue(settings[name]);
                }
            }

            for (name in fields.items) {
                fields.getItem(name).enable(true);
            }
        }

        function getSettings() {
            var settings = {};

            for (var name in fields.items) {
                settings[name] = fields.getItem(name).getValue();
            }

            return settings;
        }

        return {
            initialize  : initialize,
            reload      : reload,
            getSettings : getSettings
        }
    })();


    var namespaces = (function () {
        var list       = null;
        var fields     = new NamedObjectHash();
        var namespaces = new NameHash(new DefaultNamespace());
        var current    = namespaces.getItem('');
        var defaults   = new Hash(
            'refnote-id'           , 'numeric',
            'reference-base'       , 'super',
            'reference-font-weight', 'normal',
            'reference-font-style' , 'normal',
            'reference-format'     , 'right-parent',
            'reference-render'     , 'basic',
            'multi-ref-id'         , 'ref-counter',
            'note-preview'         , 'popup',
            'notes-separator'      , '100%',
            'note-text-align'      , 'justify',
            'note-font-size'       , 'normal',
            'note-render'          , 'basic',
            'note-id-base'         , 'super',
            'note-id-font-weight'  , 'normal',
            'note-id-font-style'   , 'normal',
            'note-id-format'       , 'right-parent',
            'back-ref-caret'       , 'none',
            'back-ref-base'        , 'super',
            'back-ref-font-weight' , 'bold',
            'back-ref-font-style'  , 'normal',
            'back-ref-format'      , 'note-id',
            'back-ref-separator'   , 'comma',
            'scoping'              , 'reset'
        );

        function DefaultNamespace() {
            this.isReadOnly = function () {
                return true;
            }

            this.setName = function (newName) {
            }

            this.getName = function () {
                return '';
            }

            this.setStyle = function (name, value) {
            }

            this.getStyle = function (name) {
                return defaults.getItem(name);
            }

            this.getStyleInheritance = function (name) {
                return 'default';
            }

            this.getSettings = function () {
                return {};
            }
        }

        function Namespace(name, data) {
            var styles = new Hash(data);

            function getParent() {
                var parent = name.replace(/\w*:$/, '');

                while (!namespaces.hasItem(parent)) {
                    parent = parent.replace(/\w*:$/, '');
                }

                return namespaces.getItem(parent);
            }

            this.isReadOnly = function () {
                return false;
            }

            this.setName = function (newName) {
                name = newName;
            }

            this.getName = function () {
                return name;
            }

            this.setStyle = function (name, value) {
                if (value == 'inherit') {
                    styles.removeItem(name);
                }
                else {
                    styles.setItem(name, value);
                }
            }

            this.getStyle = function (name) {
                var result;

                if (styles.hasItem(name)) {
                    result = styles.getItem(name);
                }
                else {
                    result = getParent().getStyle(name);
                }

                return result;
            }

            this.getStyleInheritance = function (name) {
                var result = '';

                if (!styles.hasItem(name)) {
                    result = getParent().getStyleInheritance(name) || 'inherited';
                }

                return result;
            }

            this.getSettings = function () {
                var settings = {};

                for (var name in styles.items) {
                    settings[name] = styles.getItem(name);
                }

                return settings;
            }
        }

        function Field(styleName) {
            this.element = jQuery('#field-' + styleName);

            this.getName = function () {
                return styleName;
            }

            this.updateInheretance = function () {
                this.element.parents('td')
                    .removeClass('default inherited')
                    .addClass(current.getStyleInheritance(styleName));
            }
        }

        function SelectField(styleName) {
            this.baseClass = Field;
            this.baseClass(styleName);

            var combo = this.element;

            combo.change(this, function (event) {
                event.data.onChange();
            });

            function setSelection(value) {
                combo.children('[value="' + value + '"]').attr('selected', 'selected');
            }

            this.onChange = function () {
                var value = combo.val();

                current.setStyle(styleName, value);

                this.updateInheretance();

                if ((value == 'inherit') || current.isReadOnly()) {
                    setSelection(current.getStyle(styleName));
                }

                modified = true;
            }

            this.update = function () {
                this.updateInheretance();
                setSelection(current.getStyle(styleName));
                combo.prop('disabled', current.isReadOnly());
            }
        }

        function TextField(styleName, validate) {
            this.baseClass = Field;
            this.baseClass(styleName);

            var edit   = this.element;
            var button = jQuery('#field-' + styleName + '-inherit');

            edit.change(this, function (event) {
                event.data.setValue(validate(edit.val()));
            });

            button.click(this, function (event) {
                event.data.setValue('inherit');
            });

            this.setValue = function (value) {
                current.setStyle(styleName, value);

                this.updateInheretance();

                if ((edit.val() != value) || (value == 'inherit') || current.isReadOnly()) {
                    edit.val(current.getStyle(styleName));
                }

                modified = true;
            }

            this.update = function () {
                this.updateInheretance();

                edit.val(current.getStyle(styleName));
                edit.prop('disabled', current.isReadOnly());
                button.prop('disabled', current.isReadOnly());
            }
        }

        function initialize() {
            list = new List('#select-namespaces');

            fields.addItem(new SelectField('refnote-id'));
            fields.addItem(new SelectField('reference-base'));
            fields.addItem(new SelectField('reference-font-weight'));
            fields.addItem(new SelectField('reference-font-style'));
            fields.addItem(new SelectField('reference-format'));
            fields.addItem(new SelectField('reference-render'));
            fields.addItem(new SelectField('multi-ref-id'));
            fields.addItem(new SelectField('note-preview'));
            fields.addItem(new TextField('notes-separator', function (value) {
                return (value.match(/(?:\d+\.?|\d*\.\d+)(?:%|em|px)|none/) != null) ? value : 'none';
            }));
            fields.addItem(new SelectField('note-text-align'));
            fields.addItem(new SelectField('note-font-size'));
            fields.addItem(new SelectField('note-render'));
            fields.addItem(new SelectField('note-id-base'));
            fields.addItem(new SelectField('note-id-font-weight'));
            fields.addItem(new SelectField('note-id-font-style'));
            fields.addItem(new SelectField('note-id-format'));
            fields.addItem(new SelectField('back-ref-caret'));
            fields.addItem(new SelectField('back-ref-base'));
            fields.addItem(new SelectField('back-ref-font-weight'));
            fields.addItem(new SelectField('back-ref-font-style'));
            fields.addItem(new SelectField('back-ref-format'));
            fields.addItem(new SelectField('back-ref-separator'));
            fields.addItem(new SelectField('scoping'));

            jQuery('#select-namespaces').change(onNamespaceChange);
            jQuery('#name-namespaces').prop('disabled', true);
            jQuery('#add-namespaces').click(onAddNamespace).prop('disabled', true);
            jQuery('#rename-namespaces').click(onRenameNamespace).prop('disabled', true);
            jQuery('#delete-namespaces').click(onDeleteNamespace).prop('disabled', true);

            updateFields();
        }

        function onNamespaceChange(event) {
            setCurrent(list.getSelectedValue());
        }

        function onAddNamespace(event) {
            try {
                var name = validateName(jQuery('#name-namespaces').val(), 'ns', namespaces);

                namespaces.setItem(name, new Namespace(name));

                setCurrent(list.insertValue(name));

                modified = true;
            }
            catch (error) {
                alert(error);
            }
        }

        function onRenameNamespace(event) {
            try {
                var newName = validateName(jQuery('#name-namespaces').val(), 'ns', namespaces);
                var oldName = current.getName();

                current.setName(newName);

                namespaces.removeItem(oldName);
                namespaces.setItem(newName, current);

                setCurrent(list.renameValue(oldName, newName));

                modified = true;
            }
            catch (error) {
                alert(error);
            }
        }

        function onDeleteNamespace(event) {
            if (confirm(locale.getString('delete_ns', current.getName()))) {
                namespaces.removeItem(current.getName());

                setCurrent(list.removeValue(current.getName()));

                modified = true;
            }
        }

        function reload(settings) {
            namespaces.clear();

            for (var name in settings) {
                if (name.match(/^:$|^:.+?:$/) != null) {
                    namespaces.setItem(name, new Namespace(name, settings[name]));
                }
            }

            jQuery('#name-namespaces').prop('disabled', false);
            jQuery('#add-namespaces').prop('disabled', false);

            setCurrent(list.reload(namespaces));
        }

        function setCurrent(name) {
            current = namespaces.getItem(name);

            updateFields();
        }

        function updateFields() {
            jQuery('#name-namespaces').val(current.getName());
            jQuery('#rename-namespaces').prop('disabled', current.isReadOnly());
            jQuery('#delete-namespaces').prop('disabled', current.isReadOnly());

            for (var name in fields.items) {
                fields.getItem(name).update();
            }
        }

        function getSettings() {
            var settings = {};

            for (var name in namespaces.items) {
                settings[name] = namespaces.getItem(name).getSettings();
            }

            return settings;
        }

        return {
            initialize  : initialize,
            reload      : reload,
            getSettings : getSettings
        }
    })();


    var notes = (function () {
        var list     = null;
        var fields   = new NamedObjectHash();
        var notes    = new NameHash(new EmptyNote());
        var current  = notes.getItem('');
        var defaults = new Hash(
            'inline'                   , false,
            'use-reference-base'       , true,
            'use-reference-font-weight', true,
            'use-reference-font-style' , true,
            'use-reference-format'     , true
        );
        var inlineAttributes = [
            'use-reference-base',
            'use-reference-font-weight',
            'use-reference-font-style',
            'use-reference-format'
        ];

        function isInlineAttribute(name) {
            return inlineAttributes.indexOf(name) != -1;
        }

        function EmptyNote() {
            this.isReadOnly = function () {
                return true;
            }

            this.setName = function (newName) {
            }

            this.getName = function () {
                return '';
            }

            this.setText = function (text) {
            }

            this.getText = function () {
                return '';
            }

            this.setAttribute = function (name, value) {
            }

            this.getAttribute = function (name) {
                return defaults.getItem(name);
            }

            this.getSettings = function () {
                return {};
            }
        }

        function Note(name, data) {
            var attributes = new Hash(data);

            this.isReadOnly = function () {
                return false;
            }

            this.setName = function (newName) {
                name = newName;
            }

            this.getName = function () {
                return name;
            }

            this.setText = function (text) {
                attributes.setItem('text', text);
            }

            this.getText = function () {
                return attributes.getItem('text');
            }

            this.setAttribute = function (name, value) {
                attributes.setItem(name, value);
            }

            this.getAttribute = function (name) {
                if (!attributes.hasItem(name) || (isInlineAttribute(name) && !this.getAttribute('inline'))) {
                    return defaults.getItem(name);
                }
                else {
                    return attributes.getItem(name);
                }
            }

            this.getSettings = function () {
                var settings = {};

                if (!this.getAttribute('inline')) {
                    for (var i in inlineAttributes) {
                        if (attributes.hasItem(inlineAttributes[i])) {
                            attributes.removeItem(inlineAttributes[i]);
                        }
                    }
                }

                for (var name in attributes.items) {
                    settings[name] = attributes.getItem(name);
                }

                return settings;
            }
        }

        function Field(attributeName) {
            this.element = jQuery('#field-' + attributeName);

            this.element.change(this, function (event) {
                current.setAttribute(attributeName, event.data.getValue());
                modified = true;
            });

            this.getName = function () {
                return attributeName;
            }

            this.enable = function (enable) {
                this.element.prop('disabled', !enable);
            }
        }

        function CheckField(attributeName) {
            this.baseClass = Field;
            this.baseClass(attributeName);

            this.setValue = function (value) {
                this.element.attr('checked', value);
            }

            this.getValue = function () {
                return this.element.is(':checked');
            }

            this.update = function () {
                this.setValue(current.getAttribute(attributeName));
                this.enable(!current.isReadOnly() && (!isInlineAttribute(attributeName) || current.getAttribute('inline')));
            }
        }

        function InlineField() {
            this.baseClass = CheckField;
            this.baseClass('inline');

            this.element.change(this, function (event) {
                for (var i in inlineAttributes) {
                    fields.getItem(inlineAttributes[i]).update();
                }
            });
        }

        function initialize() {
            list = new List('#select-notes');

            fields.addItem(new InlineField());
            fields.addItem(new CheckField('use-reference-base'));
            fields.addItem(new CheckField('use-reference-font-weight'));
            fields.addItem(new CheckField('use-reference-font-style'));
            fields.addItem(new CheckField('use-reference-format'));

            jQuery('#select-notes').change(onNoteChange);
            jQuery('#name-notes').prop('disabled', true);
            jQuery('#add-notes').click(onAddNote).prop('disabled', true);
            jQuery('#rename-notes').click(onRenameNote).prop('disabled', true);
            jQuery('#delete-notes').click(onDeleteNote).prop('disabled', true);
            jQuery('#field-note-text').change(onTextChange);

            updateFields();
        }

        function onNoteChange(event) {
            setCurrent(list.getSelectedValue());
        }

        function onAddNote(event) {
            try {
                var name = validateName(jQuery('#name-notes').val(), 'note', notes);

                notes.setItem(name, new Note(name));

                setCurrent(list.insertValue(name));

                modified = true;
            }
            catch (error) {
                alert(error);
            }
        }

        function onRenameNote(event) {
            try {
                var newName = validateName(jQuery('#name-notes').val(), 'note', notes);
                var oldName = current.getName();

                current.setName(newName);

                notes.removeItem(oldName);
                notes.setItem(newName, current);

                setCurrent(list.renameValue(oldName, newName));

                modified = true;
            }
            catch (error) {
                alert(error);
            }
        }

        function onDeleteNote(event) {
            if (confirm(locale.getString('delete_note', current.getName()))) {
                notes.removeItem(current.getName());

                setCurrent(list.removeValue(current.getName()));

                modified = true;
            }
        }

        function onTextChange(event) {
            current.setText(event.target.value);

            modified = true;
        }

        function reload(settings) {
            notes.clear();

            for (var name in settings) {
                if (name.match(/^:.+?\w$/) != null) {
                    notes.setItem(name, new Note(name, settings[name]));
                }
            }

            jQuery('#name-notes').prop('disabled', false);
            jQuery('#add-notes').prop('disabled', false);

            setCurrent(list.reload(notes));
        }

        function setCurrent(name) {
            current = notes.getItem(name);

            updateFields();
        }

        function updateFields() {
            jQuery('#name-notes').val(current.getName());
            jQuery('#rename-notes').prop('disabled', current.isReadOnly());
            jQuery('#delete-notes').prop('disabled', current.isReadOnly());
            jQuery('#field-note-text').val(current.getText()).prop('disabled', current.isReadOnly());

            for (var name in fields.items) {
                fields.getItem(name).update();
            }
        }

        function getSettings() {
            var settings = {};

            for (var name in notes.items) {
                settings[name] = notes.getItem(name).getSettings();
            }

            return settings;
        }

        return {
            initialize  : initialize,
            reload      : reload,
            getSettings : getSettings
        }
    })();


    function initialize() {
        locale.initialize();
        general.initialize();
        namespaces.initialize();
        notes.initialize();

        jQuery('#save-config').click(function () {
            saveSettings();
        });

        window.onbeforeunload = onBeforeUnload;

        jQuery('#server-status').show();

        server.loadSettings();
    }

    function reloadSettings(settings) {
        general.reload(settings.general);
        namespaces.reload(settings.namespaces);
        notes.reload(settings.notes);
    }

    function saveSettings() {
        var settings = {};

        settings.general    = general.getSettings();
        settings.namespaces = namespaces.getSettings();
        settings.notes      = notes.getSettings();

        server.saveSettings(settings);

        scroll(0, 0);
    }

    function onBeforeUnload(event) {
        if (modified) {
            var message = locale.getString('unsaved');

            (event || window.event).returnValue = message;

            return message;
        }
    }

    function validateName(name, type, existing) {
        var names = name.split(':');

        name = (type == 'ns') ? ':' : '';

        for (var i = 0; i < names.length; i++) {
            if (names[i] != '') {
                /* ECMA regexp doesn't support POSIX character classes, so [a-zA-Z] is used instead of [[:alpha:]] */
                if (names[i].match(/^[a-zA-Z]\w*$/) == null) {
                    name = '';
                    break;
                }

                name += (type == 'ns') ? names[i] + ':' : ':' + names[i];
            }
        }

        if (name == '') {
            throw locale.getString('invalid_' + type + '_name');
        }

        if (existing.hasItem(name)) {
            throw locale.getString(type + '_name_exists', name);
        }

        return name;
    }

    return {
        initialize : initialize
    }
})();


jQuery(function () {
    if (jQuery('#refnotes-config').length != 0) {
        refnotes_admin.initialize();
    }
});
