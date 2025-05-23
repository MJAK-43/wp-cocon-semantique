<?php

namespace CSB\Interfaces;

/**
 * Interface PublisherInterface
 *
 * Définit les méthodes pour publier des contenus dans WordPress.
 */
interface PublisherInterface
{
    /**
     * Publie un article dans WordPress.
     *
     * @param string $title       Le titre de l'article.
     * @param string $content     Le contenu HTML de l'article.
     * @param int|null $parentId  ID du parent si l'article est lié à un autre (optionnel).
     * @param array $metas        Métadonnées associées (niveau, structure, etc.).
     * @return int|null           L'ID du post publié ou null en cas d'échec.
     */
    public function publish(string $title, string $content, ?int $parentId = null, array $metas = []): ?int;

    /**
     * Met à jour les métadonnées personnalisées d’un article.
     *
     * @param int $postId
     * @param array $metas
     * @return void
     */
    public function updateMetas(int $postId, array $metas): void;

    /**
     * Vérifie si un article avec un titre donné existe déjà.
     *
     * @param string $title
     * @return int|null  ID du post s'il existe, sinon null.
     */
    public function getPostIdByTitle(string $title): ?int;

    /**
     * Supprime un article par ID (optionnel pour nettoyage).
     *
     * @param int $postId
     * @return bool
     */
    public function delete(int $postId): bool;
}
