<?php
if (!defined('ABSPATH')) exit;

class CSB_Linker {

    /**
     * Ajoute les permaliens WordPress aux nœuds ayant un post_id.
     */
    public function add_permalink_links(array &$tree): void {
        foreach ($tree as &$node) {
            if (!empty($node['post_id'])) {
                $node['link'] = get_permalink($node['post_id']);
            }

            if (!empty($node['children'])) {
                $this->add_permalink_links($node['children']);
            }
        }
    }

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

    public function generate_structured_links($content, int $level, string $slug, array $tree) {
        $sections = [];

        $parent = $this->get_parent_from_tree($slug, $tree);
        $siblings = $this->get_siblings_from_tree($slug, $tree);
        $root = $this->get_root_from_tree($slug, $tree);

        // Niveau 1 : aucun lien à afficher
        if ($level === 1) {
            return $content;
        }

        // Niveau 2 : afficher parent et racine
        if ($level === 2 && $parent !== null) {
            $parent_link = isset($parent['link'], $parent['click_bait'])
                ? '<a href="' . esc_url($parent['link']) . '">' . esc_html($parent['click_bait']) . '</a>'
                : null;

            $root_link = isset($root['link'], $root['click_bait'])
                ? '<a href="' . esc_url($root['link']) . '">' . esc_html($root['click_bait']) . '</a>'
                : null;

            if ($parent_link) {
                $sections[] = "<h3>👆 Article parent :</h3><ul><li>{$parent_link}</li></ul>";
            }
            if ($root_link) {
                $sections[] = "<h3>📌 Article racine :</h3><ul><li>{$root_link}</li></ul>";
            }
        }

        // Niveau 3 : afficher parent, racine et frères/sœurs
        if ($level >= 3) {
            if (!empty($parent)) {
                $parent_link = isset($parent['link'], $parent['click_bait'])
                    ? '<a href="' . esc_url($parent['link']) . '">' . esc_html($parent['click_bait']) . '</a>'
                    : null;
                if ($parent_link) {
                    $sections[] = "<h3>👆 Article parent :</h3><ul><li>{$parent_link}</li></ul>";
                }
            }

            if (!empty($siblings)) {
                $sibling_links = [];
                foreach ($siblings as $sibling) {
                    if (isset($sibling['link'], $sibling['click_bait'])) {
                        $sibling_links[] = '<a href="' . esc_url($sibling['link']) . '">' . esc_html($sibling['click_bait']) . '</a>';
                    }
                }
                if (!empty($sibling_links)) {
                    $sections[] = "<h3>👬 Articles liés :</h3><ul><li>" . implode('</li><li>', $sibling_links) . "</li></ul>";
                }
            }

            if (!empty($root)) {
                $root_link = isset($root['link'], $root['click_bait'])
                    ? '<a href="' . esc_url($root['link']) . '">' . esc_html($root['click_bait']) . '</a>'
                    : null;
                if ($root_link) {
                    $sections[] = "<h3>📌 Article racine :</h3><ul><li>{$root_link}</li></ul>";
                }
            }
        }

        if (!empty($sections)) {
            $content .= "\n\n" . implode("\n\n", $sections);
        }

        return $content;
    }

} // fin classe
