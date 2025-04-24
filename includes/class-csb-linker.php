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
    public function generate_structured_links(array $map, int $post_id): string {
        $content = '';
    
        // Trouver l'article racine (celui qui n’a pas de parent)
        $idPostRoot = $this->get_root_from_map($map);
    
        // S'assurer qu'on ne réaffiche pas un lien vers lui-même
        if ($post_id !== $idPostRoot) {
            $link = esc_url($map[$idPostRoot]['link']);
            $title = esc_html($map[$idPostRoot]['title']);
    
            $content .= "<div class='csb-links'><h3>📌 Article racine :</h3>";
            $content .= "<ul><li><a href='$link' target='_blank'>$title</a></li></ul></div>";
        }
         // Liens vers les frères et sœurs
        $siblings = $this->get_siblings_from_map($post_id, $map);
        if (!empty($siblings)) {
            $sibling_links = [];
            foreach ($siblings as $sibling) {
                if (!empty($sibling['link']) && !empty($sibling['title'])) {
                    $sibling_links[] = "<a href='" . esc_url($sibling['link']) . "'>" . esc_html($sibling['title']) . "</a>";
                }
            }

            if (!empty($sibling_links)) {
                $content .= "<div class='csb-links'><h3>Articles liés :</h3>";
                $content .= "<ul><li>" . implode("</li><li>", $sibling_links) . "</li></ul></div>";
            }
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
    public function get_siblings_from_map(int $post_id, array $map): array {
        if (!isset($map[$post_id])) return [];
    
        $parent_id = $map[$post_id]['parent_id'] ?? null;
        $siblings = [];
    
        foreach ($map as $id => $node) {
            if (
                $id !== $post_id && // exclut lui-même
                ($node['parent_id'] ?? null) === $parent_id
            ) {
                $siblings[] = $node;
            }
        }
    
        return $siblings;
    }
    
    
    
    

    /**
     * Récupère le nœud racine à partir du slug dans l’arbre.
     */
    public function get_root_from_map(array $map){
        $post_id=array_key_first($map);
        return $post_id;
    }
    

    
}
