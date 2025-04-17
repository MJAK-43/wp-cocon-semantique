<?php
if (!defined('ABSPATH')) exit;

class CSB_Publisher {

    public function publish_structure(array &$tree, int $parent_id = 0, int $level = 1) {
        foreach ($tree as $slug => &$node) {
            $this->publish_node($node, $slug, $parent_id, $level);
        }
    }

    private function publish_node(array &$node, string $slug, int $parent_id, int $level) {
        $linker = new CSB_Linker();
        $title = $node['title'];
        $content_parts = $node['content'] ?? [];

        $post_id = $this->create_post($title, $slug, $parent_id);

        if (!is_wp_error($post_id)) {
            $node['post_id'] = $post_id;

            $this->store_meta($post_id, $level, $parent_id, $slug, $node['click_bait'] ?? '');

            $html = $this->generate_html_content($content_parts,$level);

            $final_content = $this->append_freepik_image(
                $linker->generate_structured_links($html, $level, $post_id, $parent_id, $node['children'] ?? []),
                $content_parts['image_url'] ?? '',
                $content_parts['image'] ?? ''
            );

            wp_update_post([
                'ID' => $post_id,
                'post_content' => $final_content,
            ]);

            if (!empty($node['children'])) {
                $this->publish_structure($node['children'], $post_id, $level + 1);
            }
        }
    }

    private function create_post($title, $slug, $parent_id) {
        return wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'post_parent'  => $parent_id,
        ]);
    }

    private function store_meta($post_id, $level, $parent_id, $slug, $click_bait) {
        update_post_meta($post_id, '_csb_level', $level);
        update_post_meta($post_id, '_csb_parent_id', $parent_id);
        update_post_meta($post_id, '_csb_slug', $slug);
        update_post_meta($post_id, '_csb_click_bait', $click_bait);
    }

    private function generate_html_content($content_parts,$level) {
        $html = '';

        if (!empty($content_parts['intro'])) {
            $html .= '<p>' . esc_html($content_parts['intro']) . '</p>';
        }

        if (!empty($content_parts['developments'])) {
            foreach ($content_parts['developments'] as $dev) {
                $html .= '<h3>' . esc_html($dev['title']) . '</h3>';
                $html .= '<p>' . esc_html($dev['text']) . '</p>';
                if (!empty($dev['link'])&&$level!=3) {
                    $html .= '<p>' . $dev['link'] . '</p>';
                }
            }
        }

        if (!empty($content_parts['conclusion'])) {
            $html .= '<p><strong>' . esc_html($content_parts['conclusion']) . '</strong></p>';
        }

        return $html;
    }

    private function append_freepik_image($content, $image_url, $alt = '') {
        if (!$image_url || str_starts_with($image_url, '❌')) return $content;
        $img_html = '<div style="margin-top:2em;"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt) . '" style="max-width:100%; height:auto;" /></div>';
        return $content . "\n\n" . $img_html;
    }
}
