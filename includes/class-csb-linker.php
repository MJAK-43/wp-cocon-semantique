<?php
if (!defined('ABSPATH')) exit;

class CSB_Linker {

    /**
     * Ajoute les vrais permaliens WordPress aux nœuds une fois qu’ils ont un post_id.
     * @param array $tree
     */
    // public function add_permalink_links(array &$tree) {
    //     foreach ($tree as &$node) {
    //         if (!empty($node['post_id'])) {
    //             $node['link'] = get_permalink($node['post_id']);
    //             print_r($node['post_id']);
    //         }
    
    //         if (!empty($node['children'])) {
    //             $this->add_permalink_links($node['children']);
    //         }
    //     }
    // }

    public function add_permalink_links(array &$tree, array &$title_count = []) {
        foreach ($tree as &$node) {
            if (!empty($node['post_id'])) {
                $title = $node['title'];
    
                // Compter les occurrences du titre
                if (!isset($title_count[$title])) {
                    $title_count[$title] = 1;
                } else {
                    $title_count[$title]++;
                }
    
                // Créer un slug unique basé sur le titre + compteur
                $suffix = $title_count[$title] > 1 ? '-' . $title_count[$title] : '';
                $slug = sanitize_title($title . $suffix);
    
                // Générer le lien à partir du slug (en local ou base_url configurable)
                $base_url = home_url('/');
                $url = trailingslashit($base_url) . $slug;
    
                $node['link'] = $url;
            }
    
            if (!empty($node['children'])) {
                $this->add_permalink_links($node['children'], $title_count);
            }
        }
    }
    

    public function count_articles_by_exact_title($target_title) {
        $query = new WP_Query([
            'post_type'      => 'post',
            'posts_per_page' => -1,
            's'              => $target_title,
            'post_status'    => 'publish',
        ]);
    
        $count = 0;
    
        foreach ($query->posts as $post) {
            if (strcasecmp($post->post_title, $target_title) === 0) {
                $count++;
            }
        }
    
        return $count;
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
    public function generate_structured_links($content, $level, $post_id, $parent_id = 0, $children = []) {
        $sections = [];

        $parent_link = $this->get_parent_link($parent_id);
        if ($parent_link && $level != 1) {
            $sections[] = "<h3>👆 Article parent :</h3><ul><li>{$parent_link}</li></ul>";
        } else {
            $content .= "Aucun parent";
        }

        $sibling_links = $this->get_sibling_links($post_id, $parent_id);
        if (!empty($sibling_links) && $level != 1) {
            $sections[] = "<h3>👬 Articles liés :</h3><ul><li>" . implode('</li><li>', $sibling_links) . '</li></ul>';
        } else {
            $content .= "Aucun sibling";
        }

        if (!empty($sections)) {
            $content .= "\n\n" . implode("\n\n", $sections);
        }

        return $content;
    }
}
