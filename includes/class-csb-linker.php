<?php
if (!defined('ABSPATH')) exit;

class CSB_Linker {

    
    
    public function add_permalink_links(array &$tree) {
        foreach ($tree as &$node) {
            if (!empty($node['post_id'])) {
                $node['link'] = get_permalink($node['post_id']);
            }
    
            if (!empty($node['children'])) {
                $this->add_permalink_links($node['children']);
            }
        }
    }

    public function add_links_to_developments(array &$tree): void {
        foreach ($tree as &$node) {
            if (!empty($node['content']['developments']) && !empty($node['children'])) {
                foreach ($node['content']['developments'] as &$dev) {
                    foreach ($node['children'] as &$child) {
                        if (
                            isset($child['title'], $child['link'], $child['click_bait']) &&
                            trim($child['title']) === trim($dev['title'])
                        ) {
                            // 💡 Ajoute le lien dans le développement
                            $dev['link'] = '<a href="' . esc_url($child['link']) . '">' . esc_html($child['click_bait']) . '</a>';
                            break;
                        }
                        else{
                            // echo "<br>";echo "<br>";echo "<br>";
                            // print_r(trim($child['title']));
                            // echo "<br>";
                            // print_r(trim($dev['title']));
                        }
                    }
                }
            }
    
            if (!empty($node['children'])) {
                $this->add_links_to_developments($node['children']);
            }
        }
    }
    
    
    
    

   
    

    /**
     * Retourne un lien vers le parent avec son click_bait
     */
    private function get_parent_link(int $parent_id): ?string {
        if ($parent_id) {
            $parent_url = get_permalink($parent_id);
            $parent_click_bait = get_post_meta($parent_id, '_csb_click_bait', true);
            if ($parent_url && $parent_click_bait) {
                return '<a href="' . esc_url($parent_url) . '">' . esc_html($parent_click_bait) . '</a>';
            }
        }
        return null;
    }

    /**
     * Retourne un tableau de liens vers les frères et sœurs avec leur click_bait
     */
    private function get_sibling_links(int $post_id, int $parent_id): array {
        $links = [];
        $siblings = get_children(['post_parent' => $parent_id, 'post_type' => 'post']);
        foreach ($siblings as $sibling) {
            if ((int)$sibling->ID !== (int)$post_id) {
                $url = get_permalink($sibling->ID);
                $label = get_post_meta($sibling->ID, '_csb_click_bait', true);
                if ($url && $label) {
                    $links[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
                }
            }
        }
        return $links;
    }

    /**
     * Ajoute les liens internes séparés avec sections enfants / parent / frères
     */
    /**
     * Génère les sections de liens internes selon le niveau de l'article.
     */
    public function generate_structured_links(string $slug, string $content, int $level, array $tree): string {
        $sections = [];

        $parent = $this->get_parent_from_tree($slug, $tree);
        $siblings = $this->get_siblings_from_tree($slug, $tree);
        $root = $this->get_root_from_tree($slug, $tree);

        // Niveau 1 : aucun lien
        if ($level === 1){
            print_r($slug);
            return $content;
        }

        // Niveau 2 : parent + racine
        if ($level === 2) {
            if ($parent && isset($parent['link'], $parent['click_bait'])) {
                $sections[] = "<h3>👆 Article parent :</h3><ul><li><a href='{$parent['link']}'>" . esc_html($parent['click_bait']) . "</a></li></ul>";
            }
            if ($root && isset($root['link'], $root['click_bait'])) {
                $sections[] = "<h3>📌 Article racine :</h3><ul><li><a href='{$root['link']}'>" . esc_html($root['click_bait']) . "</a></li></ul>";
            }
        }

        // Niveau 3 : parent + racine + siblings
        if ($level >= 3) {
            if ($parent && isset($parent['link'], $parent['click_bait'])) {
                $sections[] = "<h3>👆 Article parent :</h3><ul><li><a href='{$parent['link']}'>" . esc_html($parent['click_bait']) . "</a></li></ul>";
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

            if ($root && isset($root['link'], $root['click_bait'])) {
                $sections[] = "<h3>📌 Article racine :</h3><ul><li><a href='{$root['link']}'>" . esc_html($root['click_bait']) . "</a></li></ul>";
            }
        }

        if (!empty($sections)) {
            $content .= "\n\n" . implode("\n\n", $sections);
        }

        return $content;
    }



    /**
     * Récupère le parent d’un nœud donné dans l’arbre.
     */
    public function get_parent_from_tree(string $target_slug, array $tree, array $parents = []): ?array {
        foreach ($tree as $slug => $node) {
            if ($slug === $target_slug) {
                return end($parents) ?: null;
            }
    
            if (!empty($node['children'])) {
                $parents[$slug] = $node;
                $result = $this->get_parent_from_tree($target_slug, $node['children'], $parents);
                if ($result !== null) {
                    return $result;
                }
            }
        }
    
        return null;
    }
    

    /**
     * Récupère les frères et sœurs du nœud dans l’arbre.
     */
    public function get_siblings_from_tree(string $target_slug, array $tree, array $parents = []): array {
        foreach ($tree as $slug => $node) {
            if ($slug === $target_slug) {
                $last_parent = end($parents);
                if (!empty($last_parent['children'])) {
                    $siblings = [];
                    foreach ($last_parent['children'] as $sibling_slug => $sibling_node) {
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
                $result = $this->get_siblings_from_tree($target_slug, $node['children'], $parents);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
    
        return [];
    }
    

    /**
     * Récupère le nœud racine à partir du slug dans l’arbre.
     */
    public function get_root_from_tree(string $target_slug, array $tree, array $path = []): ?array {
        foreach ($tree as $slug => $node) {
            $new_path = $path;
            $new_path[] = $node;
    
            if ($slug === $target_slug) {
                return $path[0] ?? $node;
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
