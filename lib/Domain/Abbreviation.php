<?php

/**
 * Snippets AddOn - Abbreviation Entity
 *
 * @package redaxo\snippets
 */

namespace FriendsOfREDAXO\Snippets\Domain;

/**
 * Domain-Modell für Abkürzungen/Akronyme
 */
class Abbreviation
{
    private int $id;
    private string $abbr;
    private string $title;
    private ?string $description;
    private ?string $language;
    private bool $caseSensitive;
    private bool $wholeWord;
    private string $scopeContext;
    private ?array $scopeTemplates;
    private ?array $scopeCategories;
    private ?string $scopeUrlPattern;
    private int $priority;
    private bool $status;
    private string $createdate;
    private string $updatedate;
    private string $createuser;
    private string $updateuser;

    /**
     * Konstanten für Kontexte
     */
    public const CONTEXT_FRONTEND = 'frontend';
    public const CONTEXT_BACKEND = 'backend';
    public const CONTEXT_BOTH = 'both';

    /**
     * Konstanten für Status
     */
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;

    /**
     * Erstellt Abbreviation aus Array
     */
    public static function fromArray(array $data): self
    {
        $abbr = new self();
        
        $abbr->id = (int) ($data['id'] ?? 0);
        $abbr->abbr = (string) ($data['abbr'] ?? '');
        $abbr->title = (string) ($data['title'] ?? '');
        $abbr->description = $data['description'] ?? null;
        $abbr->language = $data['language'] ?? null;
        $abbr->caseSensitive = (bool) ($data['case_sensitive'] ?? false);
        $abbr->wholeWord = (bool) ($data['whole_word'] ?? true);
        $abbr->scopeContext = (string) ($data['scope_context'] ?? self::CONTEXT_FRONTEND);
        
        // JSON-Arrays decodieren
        $abbr->scopeTemplates = !empty($data['scope_templates']) 
            ? json_decode($data['scope_templates'], true) 
            : null;
        $abbr->scopeCategories = !empty($data['scope_categories'])
            ? json_decode($data['scope_categories'], true)
            : null;
        
        $abbr->scopeUrlPattern = $data['scope_url_pattern'] ?? null;
        $abbr->priority = (int) ($data['priority'] ?? 10);
        $abbr->status = (bool) ($data['status'] ?? true);
        $abbr->createdate = (string) ($data['createdate'] ?? '');
        $abbr->updatedate = (string) ($data['updatedate'] ?? '');
        $abbr->createuser = (string) ($data['createuser'] ?? '');
        $abbr->updateuser = (string) ($data['updateuser'] ?? '');
        
        return $abbr;
    }

    // Getter
    public function getId(): int { return $this->id; }
    public function getAbbr(): string { return $this->abbr; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getLanguage(): ?string { return $this->language; }
    public function isCaseSensitive(): bool { return $this->caseSensitive; }
    public function isWholeWord(): bool { return $this->wholeWord; }
    public function getScopeContext(): string { return $this->scopeContext; }
    public function getScopeTemplates(): ?array { return $this->scopeTemplates; }
    public function getScopeCategories(): ?array { return $this->scopeCategories; }
    public function getScopeUrlPattern(): ?string { return $this->scopeUrlPattern; }
    public function getPriority(): int { return $this->priority; }
    public function isActive(): bool { return $this->status; }
    public function getStatus(): bool { return $this->status; }

    /**
     * Prüft ob Abkürzung für Kontext gilt
     */
    public function appliesToContext(string $context): bool
    {
        if ($this->scopeContext === self::CONTEXT_BOTH) {
            return true;
        }

        return $this->scopeContext === $context;
    }

    /**
     * Prüft ob Abkürzung für Template gilt
     */
    public function appliesToTemplate(int $templateId): bool
    {
        if (null === $this->scopeTemplates || [] === $this->scopeTemplates) {
            return true;
        }

        return in_array($templateId, $this->scopeTemplates, true);
    }

    /**
     * Prüft ob Abkürzung für Kategorie gilt
     */
    public function appliesToCategory(int $categoryId): bool
    {
        if (null === $this->scopeCategories || [] === $this->scopeCategories) {
            return true;
        }

        return in_array($categoryId, $this->scopeCategories, true);
    }

    /**
     * Prüft ob Abkürzung für URL gilt
     */
    public function appliesToUrl(string $url): bool
    {
        if (null === $this->scopeUrlPattern || '' === $this->scopeUrlPattern) {
            return true;
        }

        return (bool) preg_match($this->scopeUrlPattern, $url);
    }

    /**
     * Prüft ob Abkürzung für Sprache gilt
     */
    public function appliesToLanguage(int $clangId): bool
    {
        if (null === $this->language || '' === $this->language) {
            return true; // Gilt für alle Sprachen
        }

        $clang = \rex_clang::get($clangId);
        if (!$clang) {
            return false;
        }

        return $this->language === $clang->getCode();
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
