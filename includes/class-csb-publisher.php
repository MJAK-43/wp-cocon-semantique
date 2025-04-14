<?php
if (!defined('ABSPATH')) exit;

class CSB_Publisher {

    /**
     * Publie la structure en créant les articles hiérarchiques
     * @param array $tree Tableau arborescent avec les titres et enfants
     * @param int $parent_id ID du parent WordPress
     * @param int $level Profondeur (0 = racine)
     */
    public function publish_structure(array &$tree, $parent_id = 0, $level = 0) {
        foreach ($tree as $slug => &$node) {
            $title = $node['title'];
            $content_parts = $node['content'] ?? [];
    
            // Concatène proprement le contenu
            $html = '';
            if (!empty($content_parts['intro'])) {
                $html .= '<p>' . esc_html($content_parts['intro']) . '</p>';
            }
    
            if (!empty($content_parts['developments'])) {
                foreach ($content_parts['developments'] as $dev) {
                    $html .= '<h3>' . esc_html($dev['title']) . '</h3>';
                    $html .= '<p>' . esc_html($dev['text']) . '</p>';
                }
            }
    
            if (!empty($content_parts['conclusion'])) {
                $html .= '<p><strong>' . esc_html($content_parts['conclusion']) . '</strong></p>';
            }
    
            $image_url = $content_parts['image_url'] ?? '';
            $image_desc = $content_parts['image'] ?? '';
    
            $post_id = wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '', // temporaire, sera remplacé
                'post_status'  => 'publish',
                'post_type'    => 'post',
                'post_parent'  => $parent_id,
            ]);
    
            if (!is_wp_error($post_id)) {
                $node['post_id'] = $post_id;
    
                update_post_meta($post_id, '_csb_level', $level);
                update_post_meta($post_id, '_csb_parent_id', $parent_id);
                update_post_meta($post_id, '_csb_slug', $slug);
    
                if (!empty($node['children'])) {
                    $this->publish_structure($node['children'], $post_id, $level + 1);
                }
    
                // Ajoute les liens + image
                $final_content = $this->append_freepik_image(
                    $this->enrich_content_with_links($html, $node['children'] ?? [], $parent_id),
                    $image_url,
                    $image_desc
                );
    
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $final_content,
                ]);
            }
        }
    }
    
    
    
    private function append_freepik_image($content, $image_url, $alt = '') {
        if (!$image_url || str_starts_with($image_url, '❌')) return $content;
    
        $img_html = '<div style="margin-top:2em;"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt) . '" style="max-width:100%; height:auto;" /></div>';
        return $content . "\n\n" . $img_html;
    }

    private function append_freepik_link($content, $image_description) {
        if (!$image_description) return $content;
    
        $query = urlencode($image_description);
        $link = "https://www.freepik.com/search?format=search&query=$query";
    
        $html = '<div style="margin-top: 2em;">🖼️ <strong>Image suggérée :</strong> ';
        $html .= '<a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">';
        $html .= esc_html($image_description) . '</a></div>';
    
        return $content . "\n\n" . $html;
    }
    
    

    /**
     * Génère un slug unique à partir du titre (optionnel)
     */
    // public function generate_slug($title) {
    //     $slug = sanitize_title($title);
    //     $base = $slug;
    //     $i = 1;

    //     while (get_page_by_path($slug, OBJECT, 'post')) {
    //         $slug = $base . '-' . $i++;
    //     }

    //     return $slug;
    // }

    private function enrich_content_with_links($content, $children = [], $parent_id = 0) {
        if (!is_array($children)) $children = [];
    
        $links = [];
    
        // 🔗 Lien vers le parent
        if ($parent_id) {
            $parent_permalink = get_permalink($parent_id);
            $parent_title = get_the_title($parent_id);
            if ($parent_permalink && $parent_title) {
                $links[] = '<a href="' . esc_url($parent_permalink) . '">⬆ Retour à ' . esc_html($parent_title) . '</a>';
            }
        }
    
        // 🔗 Liens vers les enfants
        foreach ($children as $child) {
            if (!empty($child['post_id'])) {
                $url = get_permalink($child['post_id']);
                if ($url) {
                    $links[] = '<a href="' . esc_url($url) . '">' . esc_html($child['title']) . '</a>';
                }
            }
        }
    
        // 📎 Ajout à la fin du contenu
        if (!empty($links)) {
            $content .= "\n\n<h3>🔗 Liens utiles :</h3><ul><li>" . implode('</li><li>', $links) . '</li></ul>';
        }
    
        return $content;
    }    
    


}
