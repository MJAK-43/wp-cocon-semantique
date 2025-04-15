<?php
if (!defined('ABSPATH')) exit;

class CSB_Publisher {

    public function publish_structure(array &$tree, $parent_id = 0, $level = 0) {
        $linker = new CSB_Linker();

        foreach ($tree as $slug => &$node) {
            $title = $node['title'];
            $content_parts = $node['content'] ?? [];

            // 🧱 Génération du contenu HTML
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

            // 📝 Création de l'article WordPress
            $post_id = wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '',
                'post_status'  => 'publish',
                'post_type'    => 'post',
                'post_parent'  => $parent_id,
            ]);

            if (!is_wp_error($post_id)) {
                $node['post_id'] = $post_id;
            
                update_post_meta($post_id, '_csb_level', $level);
                update_post_meta($post_id, '_csb_parent_id', $parent_id);
                update_post_meta($post_id, '_csb_slug', $slug);
                update_post_meta($post_id, '_csb_click_bait', $node['click_bait'] ?? '');
            
                // ✅ GÉNÈRE D’ABORD LE CONTENU
                $final_content = $this->append_freepik_image(
                    $linker->add_links_separated($html, $post_id, $parent_id, $node['children'] ?? []),
                    $image_url,
                    $image_desc
                );
            
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $final_content,
                ]);
            
                // ✅ PUIS PUBLIE LES ENFANTS
                if (!empty($node['children'])) {
                    $this->publish_structure($node['children'], $post_id, $level + 1);
                }
            }            
            
        }
    }

    private function append_freepik_image($content, $image_url, $alt = '') {
        if (!$image_url || str_starts_with($image_url, '❌')) return $content;

        $img_html = '<div style="margin-top:2em;"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt) . '" style="max-width:100%; height:auto;" /></div>';
        return $content . "\n\n" . $img_html;
    }

    private function enrich_content_with_links($content, $children = [], $parent_id = 0) {
        if (!is_array($children)) $children = [];

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
                if ($url) {
                    $links[] = '<a href="' . esc_url($url) . '">' . esc_html($child['title']) . '</a>';
                }
            }
        }

        if (!empty($links)) {
            $content .= "\n\n<h3>🔗 Liens utiles :</h3><ul><li>" . implode('</li><li>', $links) . '</li></ul>';
        }

        return $content;
    }

}
