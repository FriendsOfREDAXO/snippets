<?php

namespace FriendsOfREDAXO\Snippets\Domain;

/**
 * Snippet Entity
 *
 * @package redaxo\snippets
 */
class Snippet
{
    private int $id;
    private string $key;
    private string $title;
    private ?string $description = null;
    private ?string $content = null;
    private string $contentType = 'html';
    private string $context = 'both';
    private bool $status = true;
    private ?int $categoryId = null;
    private bool $isMultilang = false;
    private ?string $htmlMode = null;
    private ?string $htmlSelector = null;
    private ?string $htmlPosition = null;
    private ?string $createdate = null;
    private ?string $createuser = null;
    private ?string $updatedate = null;
    private ?string $updateuser = null;
    private int $revision = 0;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $snippet = new self();
        $snippet->id = (int) ($data['id'] ?? 0);
        $snippet->key = (string) ($data['key_name'] ?? '');
        $snippet->title = (string) ($data['title'] ?? '');
        $snippet->description = $data['description'] ?? null;
        $snippet->content = $data['content'] ?? null;
        $snippet->contentType = (string) ($data['content_type'] ?? 'html');
        $snippet->context = (string) ($data['context'] ?? 'both');
        $snippet->status = (bool) ($data['status'] ?? true);
        $snippet->categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $snippet->isMultilang = (bool) ($data['is_multilang'] ?? false);
        $snippet->htmlMode = $data['html_mode'] ?? null;
        $snippet->htmlSelector = $data['html_selector'] ?? null;
        $snippet->htmlPosition = $data['html_position'] ?? null;
        $snippet->createdate = $data['createdate'] ?? null;
        $snippet->createuser = $data['createuser'] ?? null;
        $snippet->updatedate = $data['updatedate'] ?? null;
        $snippet->updateuser = $data['updateuser'] ?? null;
        $snippet->revision = (int) ($data['revision'] ?? 0);

        return $snippet;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getContent(): string
    {
        return $this->content ?? '';
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function isActive(): bool
    {
        return $this->status;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function isMultilang(): bool
    {
        return $this->isMultilang;
    }

    public function getHtmlMode(): ?string
    {
        return $this->htmlMode;
    }

    public function getHtmlSelector(): ?string
    {
        return $this->htmlSelector;
    }

    public function getHtmlPosition(): ?string
    {
        return $this->htmlPosition;
    }

    public function hasHtmlMode(): bool
    {
        return null !== $this->htmlMode && '' !== $this->htmlMode;
    }

    public function getCreateDate(): ?string
    {
        return $this->createdate;
    }

    public function getCreateUser(): ?string
    {
        return $this->createuser;
    }

    public function getUpdateDate(): ?string
    {
        return $this->updatedate;
    }

    public function getUpdateUser(): ?string
    {
        return $this->updateuser;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Gibt den Shortcode für das Snippet zurück
     */
    public function getShortcode(): string
    {
        return '[[snippet:' . $this->key . ']]';
    }
}
