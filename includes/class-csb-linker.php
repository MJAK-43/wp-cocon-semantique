<?php
if (!defined('ABSPATH')) exit;


class CSB_Linker {
    /**
     * Ajoute les liens internes en utilisant les click_baits comme texte
     */
    public function add_links_with_clickbaits($content, $current_post_id = 0, $parent_id = 0, $children = []) {
        $links = [];

        // 🔗 Lien vers le parent
        if ($parent_id) {
            $parent_permalink = get_permalink($parent_id);
            $parent_clickbait = get_post_meta($parent_id, '_csb_click_bait', true);
            if ($parent_permalink && $parent_clickbait) {
                $links[] = '<a href="' . esc_url($parent_permalink) . '">' . esc_html($parent_clickbait) . '</a>';
            }
        }

        // 🔗 Liens vers les enfants avec leur click_bait
        foreach ($children as $child) {
            if (!empty($child['post_id'])) {
                $url = get_permalink($child['post_id']);
                $click_bait = $child['click_bait'] ?? get_post_meta($child['post_id'], '_csb_click_bait', true);
                if ($url && $click_bait) {
                    $links[] = '<a href="' . esc_url($url) . '">' . esc_html($click_bait) . '</a>';
                }
            }
        }

        // 🔗 Liens vers les frères et sœurs (sauf soi-même)
        if ($parent_id) {
            $siblings = get_children(['post_parent' => $parent_id, 'post_type' => 'post']);
            foreach ($siblings as $sibling) {
                if ((int)$sibling->ID !== (int)$current_post_id) {
                    $url = get_permalink($sibling->ID);
                    $click_bait = get_post_meta($sibling->ID, '_csb_click_bait', true);
                    if ($url && $click_bait) {
                        $links[] = '<a href="' . esc_url($url) . '">' . esc_html($click_bait) . '</a>';
                    }
                }
            }
        }

        // ➕ Injecter les liens à la fin du contenu
        if (!empty($links)) {
            $content .= "\n\n<h3>🔗 À découvrir aussi :</h3><ul><li>" . implode('</li><li>', $links) . '</li></ul>';
        }

        return $content;
    }

    public function add_links_separated($content, $post_id, $parent_id = 0, $children = []) {
        $sections = [];
    
        // 🔽 Liens vers les enfants
        if (!empty($children)) {
            $links = [];
            foreach ($children as $child) {
                if (!empty($child['post_id'])) {
                    $url = get_permalink($child['post_id']);
                    $label = $child['click_bait'] ?? $child['title'] ?? '';
                    if ($url && $label) {
                        $links[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
                    }
                }
            }
            if (!empty($links)) {
                $sections[] = "<h3>👶 Articles enfants :</h3><ul><li>" . implode('</li><li>', $links) . '</li></ul>';
            }
        }
    
        // 🔼 Lien vers le parent
        if ($parent_id) {
            $parent_url = get_permalink($parent_id);
            $parent_click_bait = get_post_meta($parent_id, '_csb_click_bait', true);
            if ($parent_url && $parent_click_bait) {
                $sections[] = "<h3>👆 Article parent :</h3><ul><li><a href='" . esc_url($parent_url) . "'>" . esc_html($parent_click_bait) . "</a></li></ul>";
            }
        }
    
        // ➡️ Liens vers les frères et sœurs
        $siblings = get_children(['post_parent' => $parent_id, 'post_type' => 'post']);
        $sibling_links = [];
        foreach ($siblings as $sibling) {
            if ((int)$sibling->ID !== (int)$post_id) {
                $url = get_permalink($sibling->ID);
                $label = get_post_meta($sibling->ID, '_csb_click_bait', true);
                if ($url && $label) {
                    $sibling_links[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
                }
            }
        }
        if (!empty($sibling_links)) {
            $sections[] = "<h3>👬 Articles liés :</h3><ul><li>" . implode('</li><li>', $sibling_links) . '</li></ul>';
        }
    
        if (!empty($sections)) {
            $content .= "\n\n" . implode("\n\n", $sections);
        }
    
        return $content;
    }
    
}
