/**
 * Snippets AddOn – CKEditor-5-Plugin "snippetsAddon"
 *
 * - Toolbar-Dropdown "Snippets & Übersetzungen" mit Live-Suche, Tabs und Kategorien
 * - Einfügen als Platzhalter [[snippet:key]] bzw. [[ key ]] an der Cursorposition
 * - Parameter-Formular für Snippets mit {param}-Platzhaltern bzw. $SNIPPET_PARAMS
 * - Autovervollständigung beim Tippen von "[[" (über das Mention-Plugin)
 * - Hervorhebung vorhandener Platzhalter im Editor (nur Editing-View, gespeichertes HTML bleibt unverändert)
 *
 * Aktivierung pro CKE5-Profil über das Feld "Extra-Definition":
 *   {"externalPlugins": ["snippetsAddon"]}
 *
 * Kein Build-Schritt nötig. Abhängigkeiten: window.CKEDITOR (cke5-AddOn), rex.cke5ExternalPlugins.
 *
 * @package redaxo\snippets
 */
(function () {
    'use strict';

    var PLUGIN_NAME = 'snippetsAddon';
    var TOOLBAR_ITEM = 'snippetsAddon';
    var CLASS_NAME = 'SnippetsAddon';
    var MARKER = '[[';
    var HIGHLIGHT_GROUP = 'snippetsAddonPlaceholder';
    var MAX_RENDERED = 150;
    var MAX_MENTIONS = 12;

    var ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">' +
        '<path d="M2.5 3h4.25v1.5H4v11h2.75V17H2.5V3Zm15 0v14h-4.25v-1.5H16v-11h-2.75V3h4.25ZM7 9.25h6v1.5H7v-1.5Zm0-3h6v1.5H7v-1.5Zm0 6h4v1.5H7v-1.5Z"/>' +
        '</svg>';

    var DEFAULTS = {
        types: ['snippets', 'translations'],
        categories: [],
        toolbar: true,
        toolbarAfter: '',
        autocomplete: true,
        highlight: true,
        sprogSyntax: false
    };

    // Deutsche Fallback-Texte, falls die Registry-Konfiguration fehlt
    var FALLBACK_TEXTS = {
        button: 'Snippets & Übersetzungen',
        search: 'Suchen …',
        search_label: 'Snippets und Übersetzungen durchsuchen',
        tab_all: 'Alle',
        tab_snippets: 'Snippets',
        tab_translations: 'Übersetzungen',
        group_snippets: 'Snippets',
        group_translations: 'Übersetzungen',
        no_category: 'Ohne Kategorie',
        loading: 'Wird geladen …',
        empty: 'Es sind noch keine Einträge vorhanden.',
        no_results: 'Keine Treffer für diese Suche.',
        error: 'Die Einträge konnten nicht geladen werden.',
        retry: 'Erneut versuchen',
        more_results: 'Weitere Treffer vorhanden – bitte die Suche verfeinern.',
        results: '{0} Treffer',
        type_html: 'HTML',
        type_text: 'Text',
        type_php: 'PHP',
        type_translation: 'Übersetzung',
        missing_value: 'In dieser Sprache noch nicht übersetzt',
        params_title: 'Angaben für „{0}“',
        params_help: 'Leere Felder werden weggelassen, dann gilt der Standardwert.',
        params_insert: 'Einfügen',
        params_back: 'Zurück',
        manage: 'Verwalten',
        keyboard_hint: '↑↓ auswählen · Enter einfügen · Esc schließen',
        unknown_placeholder: 'Unbekannter Platzhalter – wird im Frontend nicht ersetzt',
        inactive_placeholder: 'Platzhalter ist deaktiviert – wird im Frontend nicht ausgegeben',
        quickedit_label: 'Snippet oder Übersetzung einfügen'
    };

    var idCounter = 0;
    var dataCache = {};

    /* ------------------------------------------------------------------ */
    /* Hilfsfunktionen                                                     */
    /* ------------------------------------------------------------------ */

    function nextId(prefix) {
        idCounter += 1;
        return 'cke5-snippets-' + prefix + '-' + idCounter;
    }

    function isObject(value) {
        return typeof value === 'object' && value !== null && !Array.isArray(value);
    }

    function toList(value) {
        if (Array.isArray(value)) {
            return value.map(function (item) { return String(item).trim(); }).filter(function (item) { return item !== ''; });
        }
        if (typeof value === 'string' || typeof value === 'number') {
            return String(value).split(',').map(function (item) { return item.trim(); }).filter(function (item) { return item !== ''; });
        }
        return [];
    }

    function getRegistryEntry() {
        if (typeof window.rex !== 'object' || window.rex === null || !isObject(window.rex.cke5ExternalPlugins)) {
            return {};
        }
        var entry = window.rex.cke5ExternalPlugins[PLUGIN_NAME];
        return isObject(entry) && isObject(entry.config) ? entry.config : {};
    }

    function t(key, args) {
        var registry = getRegistryEntry();
        var texts = isObject(registry.i18n) ? registry.i18n : {};
        var text = typeof texts[key] === 'string' && texts[key] !== '' ? texts[key] : (FALLBACK_TEXTS[key] || key);
        if (Array.isArray(args)) {
            args.forEach(function (arg, index) {
                text = text.split('{' + index + '}').join(String(arg));
            });
        }
        return text;
    }

    function resolveSettings(profileSettings, registryConfig) {
        var registry = registryConfig || getRegistryEntry();
        var settings = Object.assign({}, DEFAULTS, isObject(registry.defaults) ? registry.defaults : {}, isObject(profileSettings) ? profileSettings : {});

        settings.types = toList(settings.types).filter(function (type) {
            return type === 'snippets' || type === 'translations';
        });
        if (settings.types.length === 0) {
            settings.types = DEFAULTS.types.slice();
        }
        settings.categories = toList(settings.categories);
        settings.apiUrl = typeof registry.apiUrl === 'string' && registry.apiUrl !== '' ? registry.apiUrl : 'index.php?rex-api-call=snippets_editor_items';
        settings.toolbar = settings.toolbar !== false && settings.toolbar !== 0 && settings.toolbar !== '0';
        settings.autocomplete = settings.autocomplete !== false && settings.autocomplete !== 0 && settings.autocomplete !== '0';
        settings.highlight = settings.highlight !== false && settings.highlight !== 0 && settings.highlight !== '0';
        settings.sprogSyntax = settings.sprogSyntax === true || settings.sprogSyntax === 1 || settings.sprogSyntax === '1';

        return settings;
    }

    function normalizeSearch(value) {
        var text = String(value || '').toLowerCase();
        if (typeof text.normalize === 'function') {
            text = text.normalize('NFD').replace(/[̀-ͯ]/g, '');
        }
        return text.replace(/ß/g, 'ss');
    }

    function getCurrentClang() {
        try {
            var clang = new URLSearchParams(window.location.search).get('clang');
            if (clang && /^\d+$/.test(clang)) {
                return clang;
            }
        } catch (e) {
            // ignore
        }
        return '';
    }

    function snippetPlaceholder(key, params) {
        var text = MARKER + 'snippet:' + key;
        (params || []).forEach(function (param) {
            text += '|' + param.name + '=' + param.value;
        });
        return text + ']]';
    }

    function translationPlaceholder(key) {
        return MARKER + ' ' + key + ' ]]';
    }

    // Werte dürfen die Platzhalter-Syntax nicht aufbrechen (Parser: "|" trennt, "]" beendet)
    function sanitizeParamValue(value) {
        return String(value || '').replace(/[\[\]|]/g, '').replace(/\s+/g, ' ').trim();
    }

    /* ------------------------------------------------------------------ */
    /* Daten laden                                                         */
    /* ------------------------------------------------------------------ */

    function prepareData(raw) {
        var data = {
            types: Array.isArray(raw.types) ? raw.types : [],
            categories: {},
            categoryOrder: [],
            items: [],
            known: { snippets: {}, translations: {} },
            urls: isObject(raw.urls) ? raw.urls : {}
        };

        (Array.isArray(raw.categories) ? raw.categories : []).forEach(function (category) {
            data.categories[String(category.id)] = String(category.name);
            data.categoryOrder.push(String(category.id));
        });

        (Array.isArray(raw.snippets) ? raw.snippets : []).forEach(function (snippet) {
            var item = {
                kind: 'snippet',
                key: String(snippet.key),
                title: String(snippet.title || snippet.key),
                description: String(snippet.description || ''),
                type: String(snippet.type || 'html'),
                categoryId: String(snippet.category_id || 0),
                preview: String(snippet.preview || ''),
                params: Array.isArray(snippet.params) ? snippet.params : [],
                missing: false
            };
            item.placeholder = snippetPlaceholder(item.key);
            item.search = normalizeSearch([item.title, item.key, item.description, item.preview, data.categories[item.categoryId] || ''].join(' '));
            data.items.push(item);
        });

        (Array.isArray(raw.translations) ? raw.translations : []).forEach(function (string) {
            var item = {
                kind: 'translation',
                key: String(string.key),
                title: String(string.preview || '') !== '' ? String(string.preview) : String(string.key),
                description: '',
                type: 'translation',
                categoryId: String(string.category_id || 0),
                preview: String(string.preview || ''),
                params: [],
                missing: string.missing === true
            };
            item.placeholder = translationPlaceholder(item.key);
            item.search = normalizeSearch([item.key, item.preview, data.categories[item.categoryId] || ''].join(' '));
            data.items.push(item);
        });

        if (isObject(raw.known)) {
            data.known.snippets = isObject(raw.known.snippets) ? raw.known.snippets : {};
            data.known.translations = isObject(raw.known.translations) ? raw.known.translations : {};
        }

        return data;
    }

    function loadData(settings) {
        var params = [];
        var clang = getCurrentClang();
        if (clang !== '') {
            params.push('clang=' + encodeURIComponent(clang));
        }
        params.push('types=' + encodeURIComponent(settings.types.join(',')));
        if (settings.categories.length > 0) {
            params.push('categories=' + encodeURIComponent(settings.categories.join(',')));
        }
        var url = settings.apiUrl + (settings.apiUrl.indexOf('?') === -1 ? '?' : '&') + params.join('&');

        if (!dataCache[url]) {
            dataCache[url] = window.fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            }).then(function (response) {
                return response.json().catch(function () {
                    return {};
                }).then(function (json) {
                    if (!response.ok || json.error) {
                        throw new Error(json.error || ('HTTP ' + response.status));
                    }
                    return prepareData(json);
                });
            }).catch(function (error) {
                delete dataCache[url];
                throw error;
            });
        }

        return dataCache[url];
    }

    function filterItems(data, query, tab) {
        var terms = normalizeSearch(query).replace(/^\s*\[\[\s*/, '').replace(/^snippet:/, '').split(/\s+/).filter(function (term) {
            return term !== '';
        });

        return data.items.filter(function (item) {
            if (tab === 'snippets' && item.kind !== 'snippet') {
                return false;
            }
            if (tab === 'translations' && item.kind !== 'translation') {
                return false;
            }
            for (var i = 0; i < terms.length; i += 1) {
                if (item.search.indexOf(terms[i]) === -1) {
                    return false;
                }
            }
            return true;
        });
    }

    // Sortierung: Typ (Snippets zuerst), dann Kategorie-Reihenfolge, dann Titel
    function sortItems(data, items) {
        var order = {};
        data.categoryOrder.forEach(function (id, index) {
            order[id] = index;
        });
        return items.slice().sort(function (a, b) {
            if (a.kind !== b.kind) {
                return a.kind === 'snippet' ? -1 : 1;
            }
            var ca = Object.prototype.hasOwnProperty.call(order, a.categoryId) ? order[a.categoryId] : 99999;
            var cb = Object.prototype.hasOwnProperty.call(order, b.categoryId) ? order[b.categoryId] : 99999;
            if (ca !== cb) {
                return ca - cb;
            }
            return a.title.localeCompare(b.title, undefined, { sensitivity: 'base' });
        });
    }

    /* ------------------------------------------------------------------ */
    /* DOM-Helfer                                                          */
    /* ------------------------------------------------------------------ */

    function el(tag, className, attributes, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (attributes) {
            Object.keys(attributes).forEach(function (name) {
                if (attributes[name] !== null && attributes[name] !== undefined && attributes[name] !== false) {
                    node.setAttribute(name, attributes[name] === true ? '' : String(attributes[name]));
                }
            });
        }
        if (typeof text === 'string') {
            node.textContent = text;
        }
        return node;
    }

    /* ------------------------------------------------------------------ */
    /* Klassen (werden erst erzeugt, wenn window.CKEDITOR existiert)       */
    /* ------------------------------------------------------------------ */

    var pluginClass = null;

    function getPluginClass(cke) {
        if (pluginClass) {
            return pluginClass;
        }

        /**
         * Inhalt des Dropdown-Panels: Suchfeld (Combobox), Tabs, Listbox, Parameter-Formular.
         */
        class SnippetsPickerView extends cke.View {
            constructor(locale, plugin, dropdown) {
                super(locale);
                this.plugin = plugin;
                this.dropdown = dropdown;
                this.state = { tab: 'all', query: '', active: -1, visible: [], mode: 'list', paramsItem: null, data: null, loading: false, error: '' };
                this.setTemplate({
                    tag: 'div',
                    attributes: {
                        class: ['ck', 'ck-snippets-addon'],
                        tabindex: '-1'
                    }
                });
            }

            render() {
                super.render();
                this._build();
            }

            focus() {
                if (this.state.mode === 'params') {
                    var firstInput = this.paramsFields.querySelector('input');
                    if (firstInput) {
                        firstInput.focus();
                        return;
                    }
                }
                this.input.focus();
            }

            _build() {
                var root = this.element;
                var listId = nextId('list');
                var statusId = nextId('status');
                var hintId = nextId('hint');
                var types = this.plugin.settings.types;

                // Kopf: Suchfeld
                var head = el('div', 'ck-snippets-addon__head');
                this.input = el('input', 'ck ck-input ck-input-text ck-snippets-addon__search', {
                    type: 'search',
                    role: 'combobox',
                    'aria-label': t('search_label'),
                    'aria-expanded': 'true',
                    'aria-controls': listId,
                    'aria-autocomplete': 'list',
                    'aria-describedby': hintId,
                    autocomplete: 'off',
                    spellcheck: 'false',
                    placeholder: t('search')
                });
                head.appendChild(this.input);

                // Tabs (nur wenn beide Typen erlaubt sind)
                this.tabs = [];
                this.tablist = null;
                if (types.length > 1) {
                    var tablist = el('div', 'ck-snippets-addon__tabs', { role: 'tablist', 'aria-label': t('button') });
                    [['all', t('tab_all')], ['snippets', t('tab_snippets')], ['translations', t('tab_translations')]].forEach((entry) => {
                        var tab = el('button', 'ck-snippets-addon__tab', {
                            type: 'button',
                            role: 'tab',
                            id: nextId('tab'),
                            'aria-selected': entry[0] === 'all' ? 'true' : 'false',
                            'aria-controls': listId,
                            tabindex: entry[0] === 'all' ? '0' : '-1',
                            'data-tab': entry[0]
                        });
                        tab.appendChild(el('span', 'ck-snippets-addon__tab-label', null, entry[1]));
                        tab.appendChild(el('span', 'ck-snippets-addon__tab-count', { 'aria-hidden': 'true' }, ''));
                        tablist.appendChild(tab);
                        this.tabs.push(tab);
                    });
                    this.tablist = tablist;
                    head.appendChild(tablist);
                }
                root.appendChild(head);

                // Liste
                this.list = el('div', 'ck-snippets-addon__list', {
                    id: listId,
                    role: 'listbox',
                    'aria-label': t('button')
                });
                if (this.tabs.length > 0) {
                    this.list.setAttribute('aria-labelledby', this.tabs[0].id);
                    this.list.removeAttribute('aria-label');
                }
                root.appendChild(this.list);

                // Statuszeile (Screenreader-Ansage)
                this.status = el('div', 'ck-snippets-addon__status', { id: statusId, role: 'status', 'aria-live': 'polite' });
                root.appendChild(this.status);

                // Parameter-Formular
                this.paramsForm = el('form', 'ck-snippets-addon__params', { hidden: true, novalidate: true });
                this.paramsTitle = el('div', 'ck-snippets-addon__params-title', { id: nextId('ptitle'), role: 'heading', 'aria-level': '2' });
                this.paramsForm.setAttribute('aria-labelledby', this.paramsTitle.id);
                this.paramsForm.appendChild(this.paramsTitle);
                this.paramsForm.appendChild(el('p', 'ck-snippets-addon__params-help', null, t('params_help')));
                this.paramsFields = el('div', 'ck-snippets-addon__params-fields');
                this.paramsForm.appendChild(this.paramsFields);
                this.paramsPreview = el('code', 'ck-snippets-addon__params-preview', { 'aria-hidden': 'true' });
                this.paramsForm.appendChild(this.paramsPreview);
                var actions = el('div', 'ck-snippets-addon__params-actions');
                this.backButton = el('button', 'ck ck-button ck-snippets-addon__button', { type: 'button' }, t('params_back'));
                this.insertButton = el('button', 'ck ck-button ck-button-action ck-snippets-addon__button ck-snippets-addon__button_primary', { type: 'submit' }, t('params_insert'));
                actions.appendChild(this.backButton);
                actions.appendChild(this.insertButton);
                this.paramsForm.appendChild(actions);
                root.appendChild(this.paramsForm);

                // Fußzeile
                var footer = el('div', 'ck-snippets-addon__footer');
                footer.appendChild(el('span', 'ck-snippets-addon__hint', { id: hintId }, t('keyboard_hint')));
                this.manageLink = el('a', 'ck-snippets-addon__manage', { href: '#', target: '_blank', rel: 'noopener', hidden: true }, t('manage'));
                footer.appendChild(this.manageLink);
                root.appendChild(footer);

                this._bindEvents();
            }

            _bindEvents() {
                // Tasten, die das CKEditor-Dropdown sonst selbst auswertet (z. B. ArrowLeft schließt),
                // im Panel abfangen. Esc und Tab bleiben unberührt.
                this.element.addEventListener('keydown', (event) => {
                    if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown'].indexOf(event.key) !== -1) {
                        event.stopPropagation();
                    }
                });

                this.input.addEventListener('input', () => {
                    this.state.query = this.input.value;
                    this.state.active = 0;
                    this.refresh();
                });

                this.input.addEventListener('keydown', (event) => {
                    var count = this.state.visible.length;
                    switch (event.key) {
                        case 'ArrowDown':
                            event.preventDefault();
                            this._setActive(count === 0 ? -1 : (this.state.active + 1) % count);
                            break;
                        case 'ArrowUp':
                            event.preventDefault();
                            this._setActive(count === 0 ? -1 : (this.state.active - 1 + count) % count);
                            break;
                        case 'PageDown':
                            event.preventDefault();
                            this._setActive(Math.min(count - 1, this.state.active + 8));
                            break;
                        case 'PageUp':
                            event.preventDefault();
                            this._setActive(Math.max(0, this.state.active - 8));
                            break;
                        case 'Enter':
                            event.preventDefault();
                            if (this.state.active >= 0 && this.state.visible[this.state.active]) {
                                this._choose(this.state.visible[this.state.active]);
                            }
                            break;
                        case 'Escape':
                            // Erst Suchbegriff leeren, dann (beim zweiten Esc) schließen
                            if (this.input.value !== '') {
                                event.preventDefault();
                                event.stopPropagation();
                                this.input.value = '';
                                this.state.query = '';
                                this.state.active = 0;
                                this.refresh();
                            }
                            break;
                    }
                });

                this.tabs.forEach((tab, index) => {
                    tab.addEventListener('click', () => {
                        this._selectTab(tab.getAttribute('data-tab'));
                        this.input.focus();
                    });
                    tab.addEventListener('keydown', (event) => {
                        var target = -1;
                        if (event.key === 'ArrowRight') {
                            target = (index + 1) % this.tabs.length;
                        } else if (event.key === 'ArrowLeft') {
                            target = (index - 1 + this.tabs.length) % this.tabs.length;
                        } else if (event.key === 'Home') {
                            target = 0;
                        } else if (event.key === 'End') {
                            target = this.tabs.length - 1;
                        }
                        if (target !== -1) {
                            event.preventDefault();
                            this._selectTab(this.tabs[target].getAttribute('data-tab'));
                            this.tabs[target].focus();
                        }
                    });
                });

                // Maus: Fokus im Suchfeld lassen, Klick wählt aus
                this.list.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                });
                this.list.addEventListener('click', (event) => {
                    var option = event.target.closest('[role="option"]');
                    if (!option) {
                        var retry = event.target.closest('[data-action="retry"]');
                        if (retry) {
                            this.load(true);
                        }
                        return;
                    }
                    var index = parseInt(option.getAttribute('data-index'), 10);
                    if (this.state.visible[index]) {
                        this._choose(this.state.visible[index]);
                    }
                });
                this.list.addEventListener('mousemove', (event) => {
                    var option = event.target.closest('[role="option"]');
                    if (option) {
                        var index = parseInt(option.getAttribute('data-index'), 10);
                        if (index !== this.state.active) {
                            this._setActive(index, true);
                        }
                    }
                });

                this.backButton.addEventListener('click', () => {
                    this.showList();
                });
                this.paramsForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    this._submitParams();
                });
                this.paramsForm.addEventListener('input', () => {
                    this._updateParamsPreview();
                });
            }

            /** Wird beim Öffnen des Dropdowns aufgerufen */
            onOpen() {
                if (this.state.mode !== 'params') {
                    this.showList(true);
                }
                this.load(false);
                window.setTimeout(() => this.focus(), 0);
            }

            load(force) {
                if (this.state.data && !force) {
                    this.refresh();
                    return;
                }
                this.state.loading = true;
                this.state.error = '';
                this.refresh();
                this.plugin.getData().then((data) => {
                    this.state.data = data;
                    this.state.loading = false;
                    this._updateManageLink();
                    this.refresh();
                }, (error) => {
                    this.state.loading = false;
                    this.state.error = error && error.message ? error.message : t('error');
                    this.refresh();
                });
            }

            showList(resetQuery) {
                this.state.mode = 'list';
                this.state.paramsItem = null;
                this.paramsForm.hidden = true;
                this.list.hidden = false;
                this.input.disabled = false;
                if (resetQuery) {
                    if (this.tabs.length > 0 && this.state.tab !== 'all') {
                        this._selectTab('all');
                    }
                    this.input.value = '';
                    this.state.query = '';
                    this.state.active = 0;
                    this.list.scrollTop = 0;
                }
                this.refresh();
                this.input.focus();
            }

            showParams(item) {
                this.state.mode = 'params';
                this.state.paramsItem = item;
                this.list.hidden = true;
                this.paramsForm.hidden = false;
                this.input.disabled = true;
                this.paramsTitle.textContent = t('params_title', [item.title]);
                this.paramsFields.textContent = '';
                item.params.forEach((param) => {
                    var id = nextId('param');
                    var field = el('div', 'ck-snippets-addon__field');
                    field.appendChild(el('label', 'ck-snippets-addon__label', { for: id }, param.name));
                    field.appendChild(el('input', 'ck ck-input ck-input-text', {
                        id: id,
                        type: 'text',
                        name: param.name,
                        placeholder: param['default'] || '',
                        autocomplete: 'off'
                    }));
                    this.paramsFields.appendChild(field);
                });
                this._updateParamsPreview();
                this.status.textContent = this.paramsTitle.textContent;
                window.setTimeout(() => this.focus(), 0);
            }

            _collectParams() {
                var params = [];
                Array.prototype.forEach.call(this.paramsFields.querySelectorAll('input'), (input) => {
                    var value = sanitizeParamValue(input.value);
                    if (value !== '') {
                        params.push({ name: input.name, value: value });
                    }
                });
                return params;
            }

            _updateParamsPreview() {
                if (this.state.paramsItem) {
                    this.paramsPreview.textContent = snippetPlaceholder(this.state.paramsItem.key, this._collectParams());
                }
            }

            _submitParams() {
                var item = this.state.paramsItem;
                if (!item) {
                    return;
                }
                this.plugin.insertText(snippetPlaceholder(item.key, this._collectParams()));
                this.state.mode = 'list';
                this.dropdown.isOpen = false;
                this.plugin.focusEditor();
            }

            _choose(item) {
                if (item.kind === 'snippet' && item.params.length > 0) {
                    this.showParams(item);
                    return;
                }
                this.plugin.insertText(item.placeholder);
                this.dropdown.isOpen = false;
                this.plugin.focusEditor();
            }

            _selectTab(tab) {
                this.state.tab = tab;
                this.state.active = 0;
                this.tabs.forEach((button) => {
                    var selected = button.getAttribute('data-tab') === tab;
                    button.setAttribute('aria-selected', selected ? 'true' : 'false');
                    button.setAttribute('tabindex', selected ? '0' : '-1');
                    if (selected) {
                        this.list.setAttribute('aria-labelledby', button.id);
                    }
                });
                this.list.scrollTop = 0;
                this.refresh();
            }

            _updateManageLink() {
                var urls = this.state.data ? this.state.data.urls : {};
                var url = urls.snippets || urls.translations || '';
                if (url) {
                    this.manageLink.href = url;
                    this.manageLink.hidden = false;
                } else {
                    this.manageLink.hidden = true;
                }
            }

            _setActive(index, fromPointer) {
                var options = this.list.querySelectorAll('[role="option"]');
                if (this.state.active >= 0 && options[this.state.active]) {
                    options[this.state.active].setAttribute('aria-selected', 'false');
                    options[this.state.active].classList.remove('ck-snippets-addon__option_active');
                }
                this.state.active = index;
                if (index >= 0 && options[index]) {
                    options[index].setAttribute('aria-selected', 'true');
                    options[index].classList.add('ck-snippets-addon__option_active');
                    this.input.setAttribute('aria-activedescendant', options[index].id);
                    if (!fromPointer) {
                        options[index].scrollIntoView({ block: 'nearest' });
                    }
                } else {
                    this.input.removeAttribute('aria-activedescendant');
                }
            }

            refresh() {
                if (this.state.mode !== 'list') {
                    return;
                }
                var list = this.list;
                list.textContent = '';
                this.input.removeAttribute('aria-activedescendant');

                if (this.state.loading) {
                    list.appendChild(el('div', 'ck-snippets-addon__message', { role: 'presentation' }, t('loading')));
                    this.status.textContent = t('loading');
                    return;
                }
                if (this.state.error) {
                    var box = el('div', 'ck-snippets-addon__message ck-snippets-addon__message_error', { role: 'presentation' });
                    box.appendChild(el('span', null, null, t('error') + ' (' + this.state.error + ')'));
                    box.appendChild(el('button', 'ck ck-button ck-snippets-addon__button', { type: 'button', 'data-action': 'retry' }, t('retry')));
                    list.appendChild(box);
                    this.status.textContent = t('error');
                    return;
                }
                var data = this.state.data;
                if (!data) {
                    return;
                }

                // Reiter ausblenden, wenn der Benutzer nur einen Typ sehen darf
                if (this.tablist) {
                    this.tablist.hidden = data.types.length < 2;
                }

                var all = filterItems(data, this.state.query, 'all');
                this._updateTabCounts(all);
                var items = sortItems(data, filterItems(data, this.state.query, this.state.tab));
                var truncated = items.length > MAX_RENDERED;
                this.state.visible = items.slice(0, MAX_RENDERED);

                if (items.length === 0) {
                    list.appendChild(el('div', 'ck-snippets-addon__message', { role: 'presentation' }, data.items.length === 0 ? t('empty') : t('no_results')));
                    this.status.textContent = data.items.length === 0 ? t('empty') : t('no_results');
                    this.state.active = -1;
                    return;
                }

                var group = null;
                var groupKey = '';
                var showKindInGroup = this.state.tab === 'all' && this.plugin.settings.types.length > 1;
                this.state.visible.forEach((item, index) => {
                    var key = item.kind + ':' + item.categoryId;
                    if (key !== groupKey) {
                        groupKey = key;
                        var labelId = nextId('group');
                        var categoryName = data.categories[item.categoryId] || t('no_category');
                        var label = showKindInGroup
                            ? (item.kind === 'snippet' ? t('group_snippets') : t('group_translations')) + ' · ' + categoryName
                            : categoryName;
                        group = el('div', 'ck-snippets-addon__group', { role: 'group', 'aria-labelledby': labelId });
                        group.appendChild(el('div', 'ck-snippets-addon__group-label', { id: labelId, role: 'presentation' }, label));
                        list.appendChild(group);
                    }
                    group.appendChild(this._renderOption(item, index));
                });

                if (truncated) {
                    list.appendChild(el('div', 'ck-snippets-addon__message', { role: 'presentation' }, t('more_results')));
                }

                if (this.state.active < 0 || this.state.active >= this.state.visible.length) {
                    this.state.active = 0;
                }
                this._setActive(this.state.active);
                this.status.textContent = t('results', [items.length]);
            }

            _updateTabCounts(items) {
                if (this.tabs.length === 0) {
                    return;
                }
                var counts = { all: items.length, snippets: 0, translations: 0 };
                items.forEach((item) => {
                    counts[item.kind === 'snippet' ? 'snippets' : 'translations'] += 1;
                });
                this.tabs.forEach((tab) => {
                    tab.querySelector('.ck-snippets-addon__tab-count').textContent = String(counts[tab.getAttribute('data-tab')]);
                });
            }

            _renderOption(item, index) {
                var option = el('div', 'ck-snippets-addon__option ck-snippets-addon__option_' + item.kind, {
                    id: nextId('opt'),
                    role: 'option',
                    'aria-selected': 'false',
                    'data-index': index
                });
                var top = el('span', 'ck-snippets-addon__option-top');
                top.appendChild(el('span', 'ck-snippets-addon__option-title', null, item.title));
                if (item.kind === 'snippet') {
                    top.appendChild(el('span', 'ck-snippets-addon__badge ck-snippets-addon__badge_' + item.type, null, t('type_' + item.type)));
                } else if (item.missing) {
                    top.appendChild(el('span', 'ck-snippets-addon__badge ck-snippets-addon__badge_missing', { title: t('missing_value') }, '!'));
                }
                option.appendChild(top);
                option.appendChild(el('code', 'ck-snippets-addon__option-key', null, item.placeholder));
                var info = item.kind === 'snippet' ? (item.description || item.preview) : (item.missing ? t('missing_value') : '');
                if (info) {
                    option.appendChild(el('span', 'ck-snippets-addon__option-info', null, info));
                }
                return option;
            }
        }

        /**
         * Das eigentliche Editor-Plugin.
         */
        class SnippetsAddon extends cke.Plugin {
            static get pluginName() {
                return CLASS_NAME;
            }

            init() {
                var editor = this.editor;
                this.settings = resolveSettings(editor.config.get(PLUGIN_NAME));
                this._dropdowns = [];
                this._highlightTimer = null;
                this._highlightUid = 0;
                this._highlightDataRequested = false;
                this._data = null;

                editor.ui.componentFactory.add(TOOLBAR_ITEM, (locale) => this._createDropdown(locale));

                if (this.settings.highlight) {
                    this._initHighlight();
                }
            }

            afterInit() {
                if (this.settings.autocomplete) {
                    this._initMentionOverride();
                }
            }

            destroy() {
                window.clearTimeout(this._highlightTimer);
                super.destroy();
            }

            getData() {
                return loadData(this.settings).then((data) => {
                    this._data = data;
                    return data;
                });
            }

            /** Öffnet das Dropdown (z. B. aus dem QuickEdit-Menü). Optional direkt mit Parameter-Formular. */
            open(item) {
                var dropdown = this._findDropdown();
                if (!dropdown) {
                    return false;
                }
                dropdown.isOpen = true;
                if (item) {
                    dropdown.snippetsPicker.showParams(item);
                }
                return true;
            }

            insertText(text, range) {
                var model = this.editor.model;
                model.change((writer) => {
                    var attributes = new Map(model.document.selection.getAttributes());
                    attributes.delete('mention');
                    var node = writer.createText(text, attributes);
                    if (range) {
                        model.insertContent(node, range);
                    } else {
                        model.insertContent(node);
                    }
                });
            }

            focusEditor() {
                this.editor.editing.view.focus();
            }

            _findDropdown() {
                var visible = this._dropdowns.filter((dropdown) => dropdown.element && dropdown.element.isConnected && dropdown.element.offsetParent !== null);
                return visible[0] || this._dropdowns[0] || null;
            }

            _createDropdown(locale) {
                var editor = this.editor;
                var dropdown = cke.createDropdown(locale);
                dropdown.buttonView.set({
                    label: t('button'),
                    icon: ICON,
                    tooltip: true
                });
                dropdown.extendTemplate({
                    attributes: { class: 'ck-snippets-addon-dropdown' }
                });
                dropdown.bind('isEnabled').to(editor, 'isReadOnly', (isReadOnly) => !isReadOnly);

                var picker = new SnippetsPickerView(locale, this, dropdown);
                dropdown.snippetsPicker = picker;
                dropdown.panelView.children.add(picker);

                dropdown.on('change:isOpen', (evt, name, isOpen) => {
                    if (isOpen) {
                        picker.onOpen();
                    } else {
                        picker.state.mode = 'list';
                    }
                });

                this._dropdowns.push(dropdown);
                return dropdown;
            }

            /* ---------------- Autovervollständigung ---------------- */

            _initMentionOverride() {
                var editor = this.editor;
                var command = editor.commands.get('mention');
                if (!command) {
                    return;
                }
                // Mention würde den Text mit einem mention-Attribut (<span class="mention">) speichern.
                // Für unsere Einträge stattdessen reinen Text einfügen.
                this.listenTo(command, 'execute', (evt, args) => {
                    var options = args && args[0] ? args[0] : {};
                    if (options.marker !== MARKER) {
                        return;
                    }
                    evt.stop();
                    var mention = options.mention || {};
                    var item = mention.snippetsItem || null;
                    var range = options.range || editor.model.document.selection.getFirstRange();

                    if (item && item.kind === 'snippet' && item.params.length > 0 && this._findDropdown()) {
                        editor.model.change((writer) => {
                            writer.remove(range);
                        });
                        // Mention setzt direkt danach den Fokus in den Editor – Dropdown erst danach öffnen
                        window.setTimeout(() => this.open(item), 0);
                        return;
                    }

                    var text = item ? item.placeholder : String(options.text || mention.id || '');
                    if (text !== '') {
                        this.insertText(text, range);
                    }
                }, { priority: 'high' });
            }

            /* ---------------- Hervorhebung ---------------- */

            _initHighlight() {
                var editor = this.editor;

                editor.conversion.for('editingDowncast').markerToHighlight({
                    model: HIGHLIGHT_GROUP,
                    view: (data) => this._highlightDescriptor(data.markerName)
                });

                this.listenTo(editor.model.document, 'change:data', () => this._scheduleHighlight());
                editor.on('ready', () => this._scheduleHighlight());
                this.listenTo(editor, 'change:isReadOnly', () => this._scheduleHighlight());
            }

            _scheduleHighlight() {
                window.clearTimeout(this._highlightTimer);
                this._highlightTimer = window.setTimeout(() => this._refreshHighlights(), 250);
            }

            _placeholderState(kind, key) {
                var data = this._data;
                var type = kind === 'snippet' ? 'snippets' : 'translations';
                if (!data || data.types.indexOf(type) === -1) {
                    return 'neutral';
                }
                var info = data.known[type][key];
                if (!info) {
                    return 'unknown';
                }
                return info.active ? 'ok' : 'inactive';
            }

            _highlightDescriptor(markerName) {
                // Name: <Gruppe>:<uid>:<kind>:<state>:<key>
                var parts = markerName.split(':');
                var kind = parts[2] || 'snippet';
                var state = parts[3] || 'neutral';
                var key = parts.slice(4).join(':');
                var classes = ['ck-snippets-ph', 'ck-snippets-ph_' + kind];
                var title = '';

                if (state === 'unknown') {
                    classes.push('ck-snippets-ph_unknown');
                    title = t('unknown_placeholder');
                } else if (state === 'inactive') {
                    classes.push('ck-snippets-ph_inactive');
                    title = t('inactive_placeholder');
                } else if (this._data) {
                    var type = kind === 'snippet' ? 'snippets' : 'translations';
                    var info = this._data.known[type] ? this._data.known[type][key] : null;
                    if (info && info.title) {
                        title = (kind === 'snippet' ? t('group_snippets') : t('group_translations')) + ': ' + info.title;
                    }
                }

                var descriptor = { classes: classes, priority: 5 };
                if (title) {
                    descriptor.attributes = { title: title };
                }
                return descriptor;
            }

            _buildPattern() {
                var source = '\\[\\[snippet:([\\w-]+)(?:\\|[^\\]]*)?\\]\\]|\\[\\[(?: ([\\w.-]+) |([\\w.-]+))\\]\\]';
                if (this.settings.sprogSyntax) {
                    source += '|\\{\\{(?: ([\\w.-]+) |([\\w.-]+))\\}\\}';
                }
                return new RegExp(source, 'g');
            }

            _refreshHighlights() {
                var editor = this.editor;
                if (!editor || editor.state === 'destroyed') {
                    return;
                }
                var model = editor.model;
                var pattern = this._buildPattern();
                var found = [];

                model.document.getRootNames().forEach((rootName) => {
                    var root = model.document.getRoot(rootName);
                    if (!root) {
                        return;
                    }
                    for (var value of model.createRangeIn(root)) {
                        var block = value.item;
                        if (value.type !== 'elementStart' || !block.is('element') || !model.schema.checkChild(block, '$text')) {
                            continue;
                        }
                        this._scanBlock(block, pattern, found);
                    }
                });

                if (found.length > 0 && !this._data && !this._highlightDataRequested) {
                    this._highlightDataRequested = true;
                    this.getData().then(() => this._scheduleHighlight(), () => {});
                }

                var desired = {};
                found.forEach((entry) => {
                    entry.state = this._placeholderState(entry.kind, entry.key);
                    entry.range = model.createRange(model.createPositionAt(entry.block, entry.start), model.createPositionAt(entry.block, entry.end));
                    entry.signature = entry.kind + ':' + entry.state + ':' + entry.key + '@' + entry.range.start.path.join(',') + '-' + entry.range.end.path.join(',') + '#' + entry.range.root.rootName;
                    desired[entry.signature] = entry;
                });

                var toRemove = [];
                var existing = {};
                for (var marker of model.markers.getMarkersGroup(HIGHLIGHT_GROUP)) {
                    var parts = marker.name.split(':');
                    var range = marker.getRange();
                    var signature = parts[2] + ':' + parts[3] + ':' + parts.slice(4).join(':') + '@' + range.start.path.join(',') + '-' + range.end.path.join(',') + '#' + range.root.rootName;
                    if (desired[signature] && !existing[signature]) {
                        existing[signature] = true;
                    } else {
                        toRemove.push(marker.name);
                    }
                }
                var toAdd = Object.keys(desired).filter((signature) => !existing[signature]).map((signature) => desired[signature]);

                if (toRemove.length === 0 && toAdd.length === 0) {
                    return;
                }

                model.change((writer) => {
                    toRemove.forEach((name) => {
                        writer.removeMarker(name);
                    });
                    toAdd.forEach((entry) => {
                        this._highlightUid += 1;
                        writer.addMarker(HIGHLIGHT_GROUP + ':' + this._highlightUid + ':' + entry.kind + ':' + entry.state + ':' + entry.key, {
                            range: entry.range,
                            usingOperation: false,
                            affectsData: false
                        });
                    });
                });
            }

            _scanBlock(block, pattern, found) {
                var text = '';
                var hasPlaceholderStart = false;
                for (var child of block.getChildren()) {
                    if (child.is('$text')) {
                        text += child.data;
                    } else {
                        text += new Array(child.offsetSize + 1).join('\u0000');
                    }
                }
                hasPlaceholderStart = text.indexOf('[[') !== -1 || (this.settings.sprogSyntax && text.indexOf('{{') !== -1);
                if (!hasPlaceholderStart) {
                    return;
                }
                pattern.lastIndex = 0;
                var match;
                while ((match = pattern.exec(text)) !== null) {
                    var kind = match[1] ? 'snippet' : 'translation';
                    var key = match[1] || match[2] || match[3] || match[4] || match[5] || '';
                    found.push({ block: block, start: match.index, end: match.index + match[0].length, kind: kind, key: key });
                }
            }
        }

        pluginClass = SnippetsAddon;
        return pluginClass;
    }

    /* ------------------------------------------------------------------ */
    /* Mention-Feed ("[[" tippen)                                          */
    /* ------------------------------------------------------------------ */

    function mentionFeed(queryText) {
        var editor = this;
        if (!editor || !editor.plugins || !editor.plugins.has(CLASS_NAME)) {
            return [];
        }
        var plugin = editor.plugins.get(CLASS_NAME);
        return plugin.getData().then(function (data) {
            return sortItems(data, filterItems(data, queryText, 'all')).slice(0, MAX_MENTIONS).map(function (item) {
                return {
                    id: item.placeholder,
                    text: item.placeholder,
                    snippetsItem: item
                };
            });
        }, function () {
            return [];
        });
    }

    function mentionItemRenderer(mention) {
        var item = mention.snippetsItem;
        var node = el('span', 'ck ck-snippets-addon-mention');
        if (!item) {
            node.textContent = mention.id;
            return node;
        }
        var top = el('span', 'ck-snippets-addon-mention__top');
        top.appendChild(el('span', 'ck-snippets-addon-mention__title', null, item.title));
        top.appendChild(el('span', 'ck-snippets-addon__badge ck-snippets-addon__badge_' + (item.kind === 'snippet' ? item.type : 'translation'), null,
            item.kind === 'snippet' ? t('type_' + item.type) : t('type_translation')));
        node.appendChild(top);
        node.appendChild(el('code', 'ck-snippets-addon-mention__key', null, item.placeholder));
        return node;
    }

    /* ------------------------------------------------------------------ */
    /* Registrierung beim cke5-AddOn                                       */
    /* ------------------------------------------------------------------ */

    function ensureToolbarItem(options, settings) {
        var toolbar = options.toolbar;
        var items = null;
        if (Array.isArray(toolbar)) {
            items = toolbar;
        } else if (isObject(toolbar) && Array.isArray(toolbar.items)) {
            items = toolbar.items;
        } else {
            options.toolbar = { items: [] };
            items = options.toolbar.items;
        }
        if (items.indexOf(TOOLBAR_ITEM) !== -1) {
            return;
        }
        var after = typeof settings.toolbarAfter === 'string' ? settings.toolbarAfter : '';
        var index = after !== '' ? items.indexOf(after) : -1;
        if (index !== -1) {
            items.splice(index + 1, 0, TOOLBAR_ITEM);
        } else {
            // vor abschließenden Trennern einfügen
            var position = items.length;
            while (position > 0 && items[position - 1] === '|') {
                position -= 1;
            }
            items.splice(position, 0, '|', TOOLBAR_ITEM);
        }
    }

    function ensureMentionFeed(options) {
        if (!isObject(options.mention)) {
            options.mention = {};
        }
        if (!Array.isArray(options.mention.feeds)) {
            options.mention.feeds = [];
        }
        var exists = options.mention.feeds.some(function (feed) {
            return isObject(feed) && feed.marker === MARKER;
        });
        if (!exists) {
            options.mention.feeds.push({
                marker: MARKER,
                feed: mentionFeed,
                itemRenderer: mentionItemRenderer,
                minimumCharacters: 0,
                dropdownLimit: MAX_MENTIONS
            });
        }
    }

    function init(ctx) {
        // Der Editor ist bereits erzeugt; alles Wesentliche passiert im Plugin selbst.
        if (ctx && ctx.element && typeof ctx.element.setAttribute === 'function') {
            ctx.element.setAttribute('data-snippets-addon', 'active');
        }
    }

    init.transformOptions = function (ctx) {
        var options = ctx && ctx.options ? ctx.options : {};
        var cke = window.CKEDITOR;
        if (typeof cke !== 'object' || cke === null || typeof cke.Plugin !== 'function' || typeof cke.View !== 'function' || typeof cke.createDropdown !== 'function') {
            console.warn('snippets: CKEditor UI library not available, plugin disabled');
            return options;
        }

        var settings = resolveSettings(options[PLUGIN_NAME], ctx.config);
        var Plugin = getPluginClass(cke);

        if (!Array.isArray(options.plugins)) {
            options.plugins = [];
        }
        if (options.plugins.indexOf(Plugin) === -1) {
            options.plugins.push(Plugin);
        }

        if (settings.toolbar) {
            ensureToolbarItem(options, settings);
        }

        if (settings.autocomplete && typeof cke.Mention === 'function') {
            ensureMentionFeed(options);
        }

        return options;
    };

    window.CKE5_EXTERNAL_PLUGINS = window.CKE5_EXTERNAL_PLUGINS || {};
    window.CKE5_EXTERNAL_PLUGINS[PLUGIN_NAME] = init;

    // QuickEdit-Menü ("/") des cke5-AddOns: Eintrag zum Öffnen des Dropdowns
    window.CKE5_QUICKEDIT_COMMANDS = window.CKE5_QUICKEDIT_COMMANDS || [];
    if (Array.isArray(window.CKE5_QUICKEDIT_COMMANDS)) {
        window.CKE5_QUICKEDIT_COMMANDS.push({
            id: 'snippetsAddon',
            label: t('quickedit_label'),
            keys: ['snippet', 'snippets', 'übersetzung', 'translation', 'platzhalter', 'placeholder'],
            icon: '[ ]',
            toolbarItem: TOOLBAR_ITEM,
            execute: function (editor) {
                if (editor && editor.plugins && editor.plugins.has(CLASS_NAME)) {
                    editor.plugins.get(CLASS_NAME).open();
                }
            }
        });
    }
})();
