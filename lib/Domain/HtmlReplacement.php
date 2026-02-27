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
    private ?string $scopeBackendRequestPattern;
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
        $instance->scopeBackendRequestPattern = isset($data['scope_backend_request_pattern']) ? (string) $data['scope_backend_request_pattern'] : null;
        $instance->scopeCategories = !empty($data['scope_categories']) 
            ? json_decode($data['scope_categories'], true) 
            : null;
        
        $instance->scopeUrlPattern = isset($data['scope_url_pattern']) ? (string) $data['scope_url_pattern'] : null;
        $instance->priority = (int) $data['priority'];
        $instance->status = self::parseBooleanValue($data['status'] ?? 0);
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
    public function getScopeBackendRequestPattern(): ?string { return $this->scopeBackendRequestPattern; }
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

        $normalizedPage = trim(strtolower($page), '/');
        if ('' === $normalizedPage) {
            return false;
        }

        foreach ($this->scopeBackendPages as $allowedPage) {
            if (!is_string($allowedPage) || '' === trim($allowedPage)) {
                continue;
            }

            $normalizedAllowedPage = trim(strtolower($allowedPage), '/');
            if ($normalizedAllowedPage === $normalizedPage) {
                return true;
            }

            if (str_starts_with($normalizedPage, $normalizedAllowedPage . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft ob Regel auf aktuellen Backend-Request passt.
     *
     * Pattern kann enthalten:
     * - Freitext (Teilstring-Match)
     * - key=value Paare getrennt durch & (z.B. page=content/edit&function=add)
     */
    public function appliesToBackendRequest(string $requestUri, array $queryParams): bool
    {
        if (null === $this->scopeBackendRequestPattern || '' === trim($this->scopeBackendRequestPattern)) {
            return true;
        }

        $patternGroups = preg_split('/\r\n|\r|\n|\|\|/', $this->scopeBackendRequestPattern) ?: [];

        foreach ($patternGroups as $patternGroup) {
            $patternGroup = trim($patternGroup);
            if ('' === $patternGroup) {
                continue;
            }

            if (self::matchesSingleBackendRequestPattern($patternGroup, $requestUri, $queryParams)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesSingleBackendRequestPattern(string $pattern, string $requestUri, array $queryParams): bool
    {
        // key=value&key2=value2 Syntax
        if (str_contains($pattern, '=')) {
            $requiredParts = explode('&', $pattern);
            foreach ($requiredParts as $requiredPart) {
                $requiredPart = trim($requiredPart);
                if ('' === $requiredPart || !str_contains($requiredPart, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $requiredPart, 2);
                $key = trim($key);
                $value = trim($value);

                if ('' === $key) {
                    continue;
                }

                $actualValue = isset($queryParams[$key]) ? (string) $queryParams[$key] : null;
                if (null === $actualValue) {
                    return false;
                }

                if ('' !== $value && $actualValue !== $value) {
                    return false;
                }
            }

            return true;
        }

        // Fallback: Teilstring auf komplette URI
        return str_contains($requestUri, $pattern);
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

    private static function parseBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = trim($value, " \t\n\r\0\x0B|");
            return '1' === $normalized;
        }

        return false;
    }
}
