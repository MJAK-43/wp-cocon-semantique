<?php
if (!defined('ABSPATH')) exit;



class CSB_Linker {
    /**
     * Ajoute les liens internes à la fin du contenu
     */
    public function add_links($content, $parent_id = 0, $children = []) {
        $links = [];

        if ($parent_id) {
            $parent_permalink = get_permalink($parent_id);
            $parent_title = get_the_title($parent_id);
            if ($parent_permalink && $parent_title) {
                $links[] = '<a href="' . esc_url($parent_permalink) . '">⬆ Retour à ' . esc_html($parent_title) . '</a>';
            }
        }

        foreach ($children as $child) {
            if (!empty($child['post_id'])) {
                $url = get_permalink($child['post_id']);
                $title = esc_html($child['title']);
                $links[] = '<a href="' . esc_url($url) . '">' . $title . '</a>';
            }
        }

        if (!empty($links)) {
            $content .= "\n\n<h3>🔗 Liens utiles :</h3><ul><li>" . implode('</li><li>', $links) . '</li></ul>';
        }

        return $content;
    }
}
