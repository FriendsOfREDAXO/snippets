<?php

namespace FriendsOfREDAXO\Snippets\Service;

/**
 * Service für Berechtigungsprüfungen
 *
 * @package redaxo\snippets
 */
class PermissionService
{
    /**
     * Prüft, ob der aktuelle User Admin-Rechte hat
     */
    public static function isAdmin(): bool
    {
        $user = \rex::getUser();
        return null !== $user && ($user->isAdmin() || $user->hasPerm('snippets[admin]'));
    }

    /**
     * Prüft, ob der aktuelle User Snippets bearbeiten darf
     */
    public static function canEdit(): bool
    {
        $user = \rex::getUser();
        return null !== $user && (
            $user->isAdmin()
            || $user->hasPerm('snippets[admin]')
            || $user->hasPerm('snippets[editor]')
        );
    }

    /**
     * Prüft, ob der aktuelle User PHP-Snippets bearbeiten darf
     */
    public static function canEditPhp(): bool
    {
        $user = \rex::getUser();
        return null !== $user && (
            $user->isAdmin()
            || $user->hasPerm('snippets[admin]')
        );
    }

    /**
     * Prüft, ob der aktuelle User Snippets ansehen darf
     */
    public static function canView(): bool
    {
        $user = \rex::getUser();
        return null !== $user && (
            $user->isAdmin()
            || $user->hasPerm('snippets[admin]')
            || $user->hasPerm('snippets[editor]')
            || $user->hasPerm('snippets[viewer]')
        );
    }
}
