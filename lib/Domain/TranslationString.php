<?php

namespace FriendsOfREDAXO\Snippets\Domain;

/**
 * TranslationString Entity – einfache String-Übersetzung (Sprog-Ersatz)
 *
 * @package redaxo\snippets
 */
class TranslationString
{
    private int $id;
    private string $key;
    private ?int $categoryId = null;
    private bool $status = true;
    private ?string $createdate = null;
    private ?string $createuser = null;
    private ?string $updatedate = null;
    private ?string $updateuser = null;

    /** @var array<int, string> clang_id => value */
    private array $values = [];

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $entity = new self();
        $entity->id = (int) ($data['id'] ?? 0);
        $entity->key = (string) ($data['key_name'] ?? '');
        $entity->categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $entity->status = (bool) ($data['status'] ?? true);
        $entity->createdate = $data['createdate'] ?? null;
        $entity->createuser = $data['createuser'] ?? null;
        $entity->updatedate = $data['updatedate'] ?? null;
        $entity->updateuser = $data['updateuser'] ?? null;

        return $entity;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function isActive(): bool
    {
        return $this->status;
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

    /**
     * Setzt die Übersetzungswerte
     *
     * @param array<int, string> $values clang_id => value
     */
    public function setValues(array $values): void
    {
        $this->values = $values;
    }

    /**
     * @return array<int, string>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Gibt den Wert für eine bestimmte Sprache zurück
     */
    public function getValue(int $clangId): string
    {
        return $this->values[$clangId] ?? '';
    }

    /**
     * Gibt den Platzhalter-Code zurück (Standard-Syntax)
     */
    public function getPlaceholder(): string
    {
        return '[[ ' . $this->key . ' ]]';
    }
}
