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
    public function generate_structured_links(array $map,int $post_id): string {
        $content='';
        $idPostRoot= $this->get_root_from_tree($map);
        print_r($idPostRoot);
        
        // if (!is_null($map[$post_id]['parent_id'])) {
        //     $link = $map[$idPostRoot][$idPostRoot]['link'] ;
        //     $title = $map[$idPostRoot][$idPostRoot]['title'] ;
        //     $content = "<h3>📌 Article racine :</h3><ul><li><a href='" . esc_url($link) . "'>" . esc_html($title) . "</a></li></ul>";
        // }
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
    public function get_root_from_tree(array $map){
        $post_id=array_key_first($map);
        return $post_id;
    }
    

    
}
