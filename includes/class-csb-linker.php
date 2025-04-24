<?php
if (!defined('ABSPATH')) exit;

class CSB_Linker {

    public function get_node_link_by_id(int $post_id): ?string {
        if (get_post_status($post_id)) {
            return get_permalink($post_id);
        }
        return null; 
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
                            $dev['link'] = '<a href="' . esc_url($child['link']) . '">' . esc_html($child['title']) . '</a>';
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
     * Génère les sections de liens internes selon le niveau de l'article.
     */
    public function generate_structured_links(string $slug, string $content, int $level, array $tree): string {
        $sections = [];
    
        //$parent = $this->get_parent_from_tree($slug, $tree);
        $siblings = $this->get_siblings_from_tree($slug, $tree);
        $root = $this->get_root_from_tree($slug, $tree);
    
        
        
        // Niveau 2 ou plus : parent + frères + racine
        if ($level >=2 ) {
            if (!empty($siblings)) {
                $sibling_links = [];
                foreach ($siblings as $sibling) {
                    if (isset($sibling['link'], $sibling['click_bait'], $sibling['title'])) {
                        $text = esc_html($sibling['click_bait']) . " (<a href='" . esc_url($sibling['link']) . "'>" . esc_html($sibling['title']) . "</a>)";
                        $sibling_links[] = $text;
                    }
                }
                if (!empty($sibling_links)) {
                    $sections[] = "<h3>👬 Articles liés :</h3><ul><li>" . implode('</li><li>', $sibling_links) . "</li></ul>";
                }
            }
    
            if ($root && isset($root['link'], $root['click_bait'], $root['title'])) {
                $text = esc_html($root['click_bait']) . " (<a href='" . esc_url($root['link']) . "'>" . esc_html($root['title']) . "</a>)";
                $sections[] = "<h3>📌 Article racine :</h3><ul><li>$text</li></ul>";
            }
        }
        else // Niveau 1 : aucun lien
            return $content;
    
        if (!empty($sections)) {
            $content .= "\n\n" . implode("\n\n", $sections);
        }
    
        return $content;
    }
    
    



    /**
     * Récupère le parent d’un nœud donné dans l’arbre.
     */
    // public function get_parent_from_tree(string $target_slug, array $tree, array $parents = []): ?array {
    //     foreach ($tree as $slug => $node) {
    //         if ($slug === $target_slug) {
    //             return end($parents) ?: null;
    //         }
    
    //         if (!empty($node['children'])) {
    //             $parents[$slug] = $node;
    //             $result = $this->get_parent_from_tree($target_slug, $node['children'], $parents);
    //             if ($result !== null) {
    //                 return $result;
    //             }
    //         }
    //     }
    
    //     return null;
    // }
    

    /**
     * Récupère les frères et sœurs du nœud dans l’arbre.
     */
    public function get_siblings_from_tree(string $target_slug, array $tree): array {
        foreach ($tree as $slug => $node) {
            if (!empty($node['children']) && is_array($node['children'])) {
                // On vérifie si l'un des enfants est le nœud cible
                if (array_key_exists($target_slug, $node['children'])) {
                    $siblings = $node['children'];
                    unset($siblings[$target_slug]); // on enlève le nœud lui-même
                    return $siblings;
                }
    
                // Sinon on continue la recherche récursive
                $result = $this->get_siblings_from_tree($target_slug, $node['children']);
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
