'use strict';

(function ($) {
    $(document).ready(function() {

        /**
         * Adapted from https://codemirror.net/demo/xmlcomplete.html
         */

        const textarea = 'o-mapper-mapping';

        var tags = {
            '!top': ['mapping'],
            '!attrs': {
            },
            mapping: {
                attrs: {
                },
                // Elements can be placed directly or in containers (params, maps, tables).
                children: ['include', 'info', 'params', 'param', 'maps', 'map', 'tables', 'table'],
            },
            include: {
                attrs: {
                    mapping: null,
                },
            },
            info: {
                attrs: {
                },
                children: ['label', 'from', 'to', 'querier', 'mapper', 'example'],
            },
            // Container for params (optional).
            params: {
                attrs: {
                },
                children: ['param'],
            },
            param: {
                attrs: {
                    name: null,
                },
            },
            // Container for maps (optional).
            maps: {
                attrs: {
                },
                children: ['map'],
            },
            map: {
                attrs: {
                },
                children: ['from', 'to', 'mod'],
            },
            from: {
                attrs: {
                    jsdot: null,
                    jmespath: null,
                    jsonpath: null,
                    xpath: null,
                    index: null,
                },
            },
            to: {
                attrs: {
                    field: null,
                    datatype: null,
                    language: null,
                    visibility: null,
                },
            },
            mod: {
                attrs: {
                    raw: null,
                    pattern: null,
                    prepend: null,
                    append: null,
                },
            },
            // Container for tables (optional).
            tables: {
                attrs: {
                },
                children: ['table'],
            },
            table: {
                attrs: {
                    name: null,
                    code: null,
                    lang: null,
                },
                children: ['label', 'entry', 'list'],
            },
            entry: {
                attrs: {
                    key: null,
                },
            },
            label: {
                attrs: {
                    lang: null,
                },
            },
            list: {
                attrs: {
                },
                children: ['term'],
            },
            term: {
                attrs: {
                    code: null,
                },
            },
            // Info children (simple text elements).
            querier: {
                attrs: {
                },
            },
            mapper: {
                attrs: {
                },
            },
            example: {
                attrs: {
                },
            },
        };

        function completeAfter(cm, pred) {
            var cur = cm.getCursor();
            if (!pred || pred()) setTimeout(function() {
                if (!cm.state.completionActive)
                    cm.showHint({completeSingle: false});
            }, 100);
            return CodeMirror.Pass;
        }

        function completeIfAfterLt(cm) {
            return completeAfter(cm, function() {
                var cur = cm.getCursor();
                return cm.getRange(CodeMirror.Pos(cur.line, cur.ch - 1), cur) == '<';
            });
        }

        function completeIfInTag(cm) {
            return completeAfter(cm, function() {
                var tok = cm.getTokenAt(cm.getCursor());
                if (tok.type == 'string' && (!/['"]/.test(tok.string.charAt(tok.string.length - 1)) || tok.string.length == 1)) return false;
                var inner = CodeMirror.innerMode(cm.getMode(), tok.state).state;
                return inner.tagName;
            });
        }

        var editor = CodeMirror.fromTextArea(document.getElementById(textarea), {
            mode: 'xml',
            matchTags: true,
            // autoCloseTags: true,
            showTrailingSpace: true,
            lineNumbers: true,
            indentUnit: 4,
            undoDepth: 1000,
            height: 'auto',
            viewportMargin: Infinity,
            extraKeys: {
                "'<'": completeAfter,
                "'/'": completeIfAfterLt,
                "' '": completeIfInTag,
                "'='": completeIfInTag,
                'Ctrl-Space': 'autocomplete'
            },
            hintOptions: {schemaInfo: tags},
            readOnly: !window.location.href.includes('/edit'),
        });

    });
})(jQuery);
