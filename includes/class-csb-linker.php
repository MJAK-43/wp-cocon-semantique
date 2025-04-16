<?php
if (!defined('ABSPATH')) exit;

class CSB_Linker {

    /**
     * Retourne un tableau de liens vers les enfants avec leur click_bait
     */
    private function get_child_links(array $children, int $parent_id): array {
        $links = [];
    
        foreach ($children as $child) {
            if (!isset($child['post_id'])) continue;
    
            // Vérifie que ce sont bien des enfants directs (sécurité supplémentaire)
            $child_parent = (int) get_post_field('post_parent', $child['post_id']);
            if ($child_parent !== $parent_id) continue;
    
            $url = get_permalink($child['post_id']);
            $label = $child['click_bait'] ?? $child['title'] ?? '';
    
            if ($url && $label) {
                $links[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
            }
        }
    
        return $links;
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


    public function get_clickbait_link(array $child): string {
        if (empty($child['post_id'])) return '';
        $url = get_permalink($child['post_id']);
        $click_bait = $child['click_bait'] ?? $child['title'] ?? '';
    
        if (!$url || !$click_bait) return '';
    
        return '<p style="margin-top:0.5em;"><a href="' . esc_url($url) . '"><em>' . esc_html($click_bait) . '</em></a></p>';
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
    public function generate_structured_links($content, $post_id, $parent_id = 0, $children = []) {
        $sections = [];

        $child_links = $this->get_child_links($children, $post_id); 
        if (!empty($child_links)) {
            $sections[] = "<h3>👶 Articles enfants :</h3><ul><li>" . implode('</li><li>', $child_links) . '</li></ul>';
        }
        else{
            $content.= "erreur enfant";
        }

        $parent_link = $this->get_parent_link($parent_id);
        if ($parent_link) {
            $sections[] = "<h3>👆 Article parent :</h3><ul><li>{$parent_link}</li></ul>";
        }else{
            $content.= "erreur parent";
        }

        $sibling_links = $this->get_sibling_links($post_id, $parent_id);
        if (!empty($sibling_links)) {
            $sections[] = "<h3>👬 Articles liés :</h3><ul><li>" . implode('</li><li>', $sibling_links) . '</li></ul>';
        }else{
            $content.= "erreur sibling";
        }

        if (!empty($sections)) {
            $content .= "\n\n" . implode("\n\n", $sections);
        }
        
        return $content;
    
    }

 
}
