<?php
if (!defined('ABSPATH')) exit;

class CSB_Publisher {
    public function register_all_posts(array &$tree, int $parent_id, int $level) {
        foreach ($tree as $slug => &$node) {
            $title = $node['title'];
            $post_id = $this->create_post($title, $parent_id);

            if (!is_wp_error($post_id)) {
                $node['post_id'] = $post_id;
                $node['link'] = get_permalink($post_id);
                $this->store_meta($post_id, $level, $parent_id, $slug, $node['click_bait'] ?? '');
            }

            if (!empty($node['children'])) {
                $this->register_all_posts($node['children'], $node['post_id'], $level + 1);
            }
        }
    }
    

    public function fill_and_publish_content(array &$tree,array $full_tree) {
        foreach ($tree as $slug => &$node) {
            $post_id = $node['post_id'] ?? 0;
            if (!$post_id) continue;

            $level = get_post_meta($post_id, '_csb_level', true);
            $parent_id = get_post_meta($post_id, '_csb_parent_id', true);
            $content_parts = $node['content'] ?? [];

            $html = $this->generate_html_content($content_parts, $level);

            $linker = new CSB_Linker();
            // $final_content = $this->append_freepik_image(
            //     $final_content = $linker->generate_structured_links($slug, $html, $level, $full_tree),
            //     $content_parts['image_url'] ?? '',
            //     $content_parts['image'] ?? ''
            // );

            wp_update_post([
                'ID' => $post_id,
                'post_content' => $html,
            ]);

            if (!empty($node['children'])) {
                $this->fill_and_publish_content($node['children'],$full_tree);
            }
        }
    }

    private function create_post($title, $parent_id) {
        /*$slug = $this->generate_unique_slug($title);*/
        $post= wp_insert_post([
            'post_title'   => $title,
            /*'post_name'    => $slug,*/
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'post_parent'  => $parent_id,
        ]);

        // echo '<br>';echo '<br>';
        // print_r($post);
        // echo '<br>';echo '<br>';
        return $post;
    }

    

    private function store_meta($post_id, $level, $parent_id, $slug, $click_bait) {
        update_post_meta($post_id, '_csb_level', $level);
        update_post_meta($post_id, '_csb_parent_id', $parent_id);
        update_post_meta($post_id, '_csb_slug', $slug);
        update_post_meta($post_id, '_csb_click_bait', $click_bait);
    }

    private function generate_html_content($content_parts, $level) {
        $html = '';

        if (!empty($content_parts['intro'])) {
            $html .= '<p>' . esc_html($content_parts['intro']) . '</p>';
        }

        if (!empty($content_parts['developments'])) {
            foreach ($content_parts['developments'] as $dev) {
                $html .= '<h3>' . esc_html($dev['title']) . '</h3>';
                $html .= '<p>' . wp_kses_post($dev['text']) . '</p>';
                if (!empty($dev['link']) && $level != 3) {
                    $html .= '<p>' . $dev['link'] . '</p>';
                    //print_r($dev['link']);
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
