<?php
if (!defined('ABSPATH')) exit;

class CSB_Linker {

    /**
     * Récupère le parent direct d'un noeud à partir de la structure complète.
     */
    public function get_parent_from_tree(string $target_slug, array $tree, array $parents = [], ?string &$found_parent_slug = null) {
        foreach ($tree as $slug => $node) {
            if ($slug === $target_slug) {
                if ($found_parent_slug !== null && isset($parents[$found_parent_slug])) {
                    return $parents[$found_parent_slug];
                }
                return null;
            }

            if (!empty($node['children'])) {
                $parents[$slug] = $node;
                $result = $this->get_parent_from_tree($target_slug, $node['children'], $parents, $slug);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Récupère les frères et soeurs d'un noeud à partir de la structure complète.
     */
    public function get_siblings_from_tree(string $target_slug, array $tree, array $parents = [], ?string &$found_parent_slug = null): array {
        foreach ($tree as $slug => $node) {
            if ($slug === $target_slug) {
                if ($found_parent_slug !== null && isset($parents[$found_parent_slug]['children'])) {
                    $siblings = [];
                    foreach ($parents[$found_parent_slug]['children'] as $sibling_slug => $sibling_node) {
                        if ($sibling_slug !== $target_slug) {
                            $siblings[$sibling_slug] = $sibling_node;
                        }
                    }
                    return $siblings;
                }
                return [];
            }

            if (!empty($node['children'])) {
                $parents[$slug] = $node;
                $result = $this->get_siblings_from_tree($target_slug, $node['children'], $parents, $slug);
                if (!empty($result)) {
                    return $result;
                }
            }
        }

        return [];
    }

    /**
     * Récupère le noeud racine d'un noeud donné en retrouvant l'origine dans l'arbre.
     */
    public function get_root_from_tree(string $target_slug, array $tree, array $path = []): ?array {
        foreach ($tree as $slug => $node) {
            $new_path = $path;
            $new_path[] = $node;

            if ($slug === $target_slug) {
                return $path[0] ?? $node; // soit premier parent, soit lui-même s'il est racine
            }

            if (!empty($node['children'])) {
                $result = $this->get_root_from_tree($target_slug, $node['children'], $new_path);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

}
