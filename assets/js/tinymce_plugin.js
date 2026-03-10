(function() {
    // TinyMCE Plugin Registration
    // This script should be added to the tinymce config via beaver:
    // extra_plugins: "snippets_rex"
    // snippets_rex: { categories: 'Category1,Category2' }

    if (typeof tinymce === 'undefined') return;

    tinymce.PluginManager.add('snippets_rex', function(editor, url) {
        // Fetch categories from editor settings
        var settings = editor.getParam('snippets_rex') || {};
        var categories = settings.categories || '';

        editor.ui.registry.addMenuButton('snippets_rex', {
            icon: 'notice',
            tooltip: 'Snippets (REDAXO)',
            fetch: function(callback) {
                var apiUrl = 'index.php?rex-api-call=snippets_tinymce_get' + (categories ? '&categories=' + encodeURIComponent(categories) : '');
                
                fetch(apiUrl)
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.length === 0) {
                            callback([{
                                type: 'menuitem',
                                text: 'Keine Snippets gefunden',
                                enabled: false
                            }]);
                            return;
                        }

                        var items = data.map(function(item) {
                            return {
                                type: 'menuitem',
                                text: item.title,
                                onAction: function() {
                                    editor.insertContent(item.content);
                                }
                            };
                        });
                        callback(items);
                    })
                    .catch(function(error) {
                        console.error('Error fetching snippets:', error);
                        callback([{
                            type: 'menuitem',
                            text: 'Fehler beim Laden',
                            enabled: false
                        }]);
                    });
            }
        });

        // Add context menu item
        editor.ui.registry.addMenuItem('snippets_rex', {
            text: 'Snippet einfügen',
            icon: 'notice',
            onAction: function() {
                // Focus the button if possible, but here we just list the command?
                // For now, only the menu button is defined
            }
        });
    });
})();
