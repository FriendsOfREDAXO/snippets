<?php

/**
 * Snippets AddOn - HTML Replacement Entity
 *
 * @package redaxo\snippets
 */

namespace FriendsOfREDAXO\Snippets\Domain;

/**
 * Domain-Modell für HTML-Ersetzungsregeln
 */
class HtmlReplacement
{
    private int $id;
    private string $name;
    private ?string $description;
    private string $type;
    private string $searchValue;
    private string $replacement;
    private string $position;
    private string $scopeContext;
    private ?array $scopeTemplates;
    private ?array $scopeBackendPages;
    private ?array $scopeCategories;
    private ?string $scopeUrlPattern;
    private int $priority;
    private bool $status;
    private string $createdate;
    private string $updatedate;
    private string $createuser;
    private string $updateuser;

    /**
     * Konstanten für Ersetzungstypen
     */
    public const TYPE_CSS_SELECTOR = 'css_selector';
    public const TYPE_HTML_MATCH = 'html_match';
    public const TYPE_REGEX = 'regex';
    public const TYPE_PHP_CALLBACK = 'php_callback';

    /**
     * Konstanten für Positionen (bei CSS-Selektor)
     */
    public const POSITION_REPLACE = 'replace';
    public const POSITION_BEFORE = 'before';
    public const POSITION_AFTER = 'after';
    public const POSITION_PREPEND = 'prepend';
    public const POSITION_APPEND = 'append';

    /**
     * Konstanten für Kontext
     */
    public const CONTEXT_FRONTEND = 'frontend';
    public const CONTEXT_BACKEND = 'backend';
    public const CONTEXT_BOTH = 'both';

    private function __construct() {}

    /**
     * Erstellt Instanz aus Datenbank-Array
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (int) $data['id'];
        $instance->name = (string) $data['name'];
        $instance->description = isset($data['description']) ? (string) $data['description'] : null;
        $instance->type = (string) $data['type'];
        $instance->searchValue = (string) $data['search_value'];
        $instance->replacement = (string) $data['replacement'];
        $instance->position = (string) $data['position'];
        $instance->scopeContext = (string) $data['scope_context'];
        
        // Parse JSON arrays
        $instance->scopeTemplates = !empty($data['scope_templates']) 
            ? json_decode($data['scope_templates'], true) 
            : null;
        $instance->scopeBackendPages = !empty($data['scope_backend_pages']) 
            ? json_decode($data['scope_backend_pages'], true) 
            : null;
        $instance->scopeCategories = !empty($data['scope_categories']) 
            ? json_decode($data['scope_categories'], true) 
            : null;
        
        $instance->scopeUrlPattern = isset($data['scope_url_pattern']) ? (string) $data['scope_url_pattern'] : null;
        $instance->priority = (int) $data['priority'];
        $instance->status = (bool) $data['status'];
        $instance->createdate = (string) $data['createdate'];
        $instance->updatedate = (string) $data['updatedate'];
        $instance->createuser = (string) $data['createuser'];
        $instance->updateuser = (string) $data['updateuser'];

        return $instance;
    }

    // Getter
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getType(): string { return $this->type; }
    public function getSearchValue(): string { return $this->searchValue; }
    public function getReplacement(): string { return $this->replacement; }
    public function getPosition(): string { return $this->position; }
    public function getScopeContext(): string { return $this->scopeContext; }
    public function getScopeTemplates(): ?array { return $this->scopeTemplates; }
    public function getScopeBackendPages(): ?array { return $this->scopeBackendPages; }
    public function getScopeCategories(): ?array { return $this->scopeCategories; }
    public function getScopeUrlPattern(): ?string { return $this->scopeUrlPattern; }
    public function getPriority(): int { return $this->priority; }
    public function isActive(): bool { return $this->status; }
    public function getCreateDate(): string { return $this->createdate; }
    public function getUpdateDate(): string { return $this->updatedate; }
    public function getCreateUser(): string { return $this->createuser; }
    public function getUpdateUser(): string { return $this->updateuser; }

    /**
     * Prüft ob Regel für aktuellen Kontext gilt
     */
    public function appliesToContext(string $context): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if ($this->scopeContext === self::CONTEXT_BOTH) {
            return true;
        }

        return $this->scopeContext === $context;
    }

    /**
     * Prüft ob Regel für Template gilt
     */
    public function appliesToTemplate(int $templateId): bool
    {
        if (null === $this->scopeTemplates || [] === $this->scopeTemplates) {
            return true; // Keine Einschränkung = gilt für alle
        }

        return in_array($templateId, $this->scopeTemplates, true);
    }

    /**
     * Prüft ob Regel für Backend-Seite gilt
     */
    public function appliesToBackendPage(string $page): bool
    {
        if (null === $this->scopeBackendPages || [] === $this->scopeBackendPages) {
            return true; // Keine Einschränkung = gilt für alle
        }

        return in_array($page, $this->scopeBackendPages, true);
    }

    /**
     * Prüft ob Regel für Kategorie gilt
     */
    public function appliesToCategory(int $categoryId): bool
    {
        if (null === $this->scopeCategories || [] === $this->scopeCategories) {
            return true; // Keine Einschränkung = gilt für alle
        }

        return in_array($categoryId, $this->scopeCategories, true);
    }

    /**
     * Prüft ob Regel für URL gilt (Regex-Pattern)
     */
    public function appliesToUrl(string $url): bool
    {
        if (null === $this->scopeUrlPattern || '' === $this->scopeUrlPattern) {
            return true; // Kein Pattern = gilt für alle
        }

        return (bool) preg_match($this->scopeUrlPattern, $url);
    }

    /**
     * Gibt verfügbare Typen zurück
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_CSS_SELECTOR => 'CSS-Selektor',
            self::TYPE_HTML_MATCH => 'HTML-Code',
            self::TYPE_REGEX => 'Regex',
            self::TYPE_PHP_CALLBACK => 'PHP Callback',
        ];
    }

    /**
     * Gibt verfügbare Positionen zurück
     */
    public static function getAvailablePositions(): array
    {
        return [
            self::POSITION_REPLACE => 'Ersetzen',
            self::POSITION_BEFORE => 'Davor einfügen',
            self::POSITION_AFTER => 'Danach einfügen',
            self::POSITION_PREPEND => 'Am Anfang einfügen',
            self::POSITION_APPEND => 'Am Ende einfügen',
        ];
    }

    /**
     * Gibt verfügbare Kontexte zurück
     */
    public static function getAvailableContexts(): array
    {
        return [
            self::CONTEXT_FRONTEND => 'Frontend',
            self::CONTEXT_BACKEND => 'Backend',
            self::CONTEXT_BOTH => 'Frontend & Backend',
        ];
    }
}
