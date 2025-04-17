<?php
if (!defined('ABSPATH')) exit;

class CSB_Linker {


    /**
     * Ajoute un champ 'link' à chaque nœud du cocon sémantique.
     *
     * @param array $tree La structure arborescente (par référence)
     * @param string $base_url L’URL de base pour générer les liens
    */
    public function add_links_to_structure(array &$tree, string $base_url = '') {
        foreach ($tree as $slug => &$node) {
            $url = trailingslashit($base_url) . $slug;
            $node['link'] = $url;

            if (!empty($node['children']) && is_array($node['children'])) {
                $this->add_links_to_structure($node['children'], $base_url);
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
    public function generate_structured_links($content,$level ,$post_id, $parent_id = 0, $children = []) {
        $sections = [];

        // $child_links = $this->get_child_links($children, $post_id); 
        // if (!empty($child_links)&&$level!=3) {
        //     $sections[] = "<h3>👶 Articles enfants :</h3><ul><li>" . implode('</li><li>', $child_links) . '</li></ul>';
        // }
        // else{
        //     $content.= "Aucun enfant";
        // }

        $parent_link = $this->get_parent_link($parent_id);
        if ($parent_link&& $level!=0) {
            $sections[] = "<h3>👆 Article parent :</h3><ul><li>{$parent_link}</li></ul>";
        }else{
            $content.= "Aucun parent";
        }

        $sibling_links = $this->get_sibling_links($post_id, $parent_id);
        if (!empty($sibling_links)&&$level!=0) {
            $sections[] = "<h3>👬 Articles liés :</h3><ul><li>" . implode('</li><li>', $sibling_links) . '</li></ul>';
        }else{
            $content.= "Aucun sibling";
        }

        if (!empty($sections)) {
            $content .= "\n\n" . implode("\n\n", $sections);
        }
        
        return $content;
    
    }

 
}
