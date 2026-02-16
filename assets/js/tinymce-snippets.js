(function() {
    'use strict';

    if (typeof tinymce === 'undefined') {
        return;
    }

    tinymce.PluginManager.add('redaxo_snippets', function(editor, url) {
        
        const openDialog = function() {
            // API-URL zusammenbauen
            let apiUrl = 'index.php?rex-api-call=snippets_tinymce_list';

            // Check if we are in the redaxo backend context
            if (window.rex && window.rex.backend) {
                apiUrl = 'index.php?rex-api-call=snippets_tinymce_list&_csrf_token=' + window.rex.csrf_token;
            }

            // Fetch snippets
            fetch(apiUrl)
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    editor.notificationManager.open({
                        text: 'Fehler beim Laden der Snippets: ' + data.error,
                        type: 'error'
                    });
                    return;
                }

                if (data.length === 0) {
                    editor.notificationManager.open({
                        text: 'Keine aktiven Snippets gefunden.',
                        type: 'warning'
                    });
                    return;
                }

                // Transform to SelectItems
                const items = data.map(item => ({
                    text: item.text,
                    value: item.value
                }));

                // Open Dialog
                editor.windowManager.open({
                    title: 'Snippet einfügen',
                    body: {
                        type: 'panel',
                        items: [
                            {
                                type: 'selectbox',
                                name: 'snippet_key',
                                label: 'Snippet auswählen',
                                items: items
                            },
                            {
                                type: 'htmlpanel', // Information
                                html: '<p style="font-size: 0.9em; color: #666;">Fügt den Platzhalter [[snippet:key]] ein.</p>'
                            }
                        ]
                    },
                    buttons: [
                        { type: 'cancel', text: 'Abbrechen' },
                        { type: 'submit', text: 'Einfügen', primary: true }
                    ],
                    onSubmit: function(api) {
                        const formData = api.getData();
                        const snippetKey = formData.snippet_key;
                        
                        if (snippetKey) {
                            // Insert Placeholder
                            editor.insertContent('[[snippet:' + snippetKey + ']]');
                        }
                        
                        api.close();
                    }
                });
            })
            .catch(error => {
                console.error('Error fetching snippets:', error);
                editor.notificationManager.open({
                    text: 'Fehler beim Laden der Snippets.',
                    type: 'error'
                });
            });
        };

        // UI Registration
        editor.ui.registry.addButton('redaxo_snippets', {
            icon: 'code-sample', // Built-in icon
            tooltip: 'Snippet einfügen',
            onAction: openDialog
        });

        editor.ui.registry.addMenuItem('redaxo_snippets', {
            icon: 'code-sample',
            text: 'Snippet einfügen...',
            onAction: openDialog
        });
    });
})();
