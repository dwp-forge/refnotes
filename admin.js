(function() {
    /*var namespaceSetting = new Hash(
        'refnote-id'           , 'numeric',
        'reference-base'       , 'super',
        'reference-font-weight', 'normal',
        'reference-font-style' , 'normal'
    );

    var namespaces = new Hash();
    var currentNamespace = '';



    function loadNamespaces() {
        //TODO: fetch data from server
        var namespace = new Hash();

        namespace.setItem('refnote-id', 'latin-lower');

        namespaces.setItem(':cite:', namespace);

        currentNamespace = ':cite:';
    }



    function updateNamespaceList() {
        var html = '';

        for (var i in namespaces.items) {
            html += '<option value="' + i + '">' + i + '</option>';
        }

        $('select-namespaces').innerHTML = html;
    }


    function updateNamespaceSetings() {
        var html = '';

        for (var i in namespaces.items) {
            html += '<option value="' + i + '">' + i + '</option>';
        }

        $('select-namespaces').innerHTML = html;
    }*/


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

            namespace.setItem('refnote-id', 'latin-lower');

            namespaces.setItem(':cite:', namespace);

            current = ':cite:';
        }

        function updateList() {
            var html = '';

            for (var i in namespaces.items) {
                html += '<option value="' + i + '">' + i + '</option>';
            }

            $('select-namespaces').innerHTML = html;
        }

        function updateSetings() {
            //alert(current);
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
