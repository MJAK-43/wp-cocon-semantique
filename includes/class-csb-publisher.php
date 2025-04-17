<?php
if (!defined('ABSPATH')) exit;

class CSB_Publisher {

    public function publish_structure(array &$tree, int $parent_id = 0, int $level = 0) {
        // echo "<br";echo "<br";echo "<br";
        // print_r($tree);
        // echo "<br";echo "<br";echo "<br";
        foreach ($tree as $slug => &$node) {
            // Appel à publish_node_simple avec le bon parent et niveau
            $this->publish_node($node, $slug, $parent_id, $level);
        }
    }
    


    private function publish_node_simple(array &$node, string $slug, int $parent_id, int $level) {
        $title = $node['title'];
        $content_parts = $node['content'] ?? [];
    
        $post_id = $this->create_post($title, $slug, $parent_id);
    
        if (!is_wp_error($post_id)) {
            $node['post_id'] = $post_id;
    
            $this->store_meta($post_id, $level, $parent_id, $slug, $node['click_bait'] ?? '');
    
            $html = $this->generate_html_content($content_parts);
    
            // Image insérée sans lien
            if (!empty($content_parts['image_url']) && !str_starts_with($content_parts['image_url'], '❌')) {
                $html .= '<div style="margin-top:2em;"><img src="' . esc_url($content_parts['image_url']) . '" alt="' . esc_attr($content_parts['image']) . '" style="max-width:100%; height:auto;" /></div>';
            }
    
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $html,
            ]);
    
            if (!empty($node['children'])) {
                foreach ($node['children'] as $child_slug => &$child) {
                    $this->publish_node_simple($child, $child_slug, $post_id, $level + 1);
                }
            }
        }
        else {
            // echo "<br><br><br>";
            // echo "❌ Erreur lors de l'insertion de l'article '$title' (slug: $slug)<br>";
        
            // if (is_wp_error($post_id)) {
            //     echo 'Message : ' . esc_html($post_id->get_error_message()) . '<br>';
            //     echo 'Code : ' . esc_html($post_id->get_error_code()) . '<br>';
            // } else {
            //     echo 'Retour inattendu de wp_insert_post()<br>';
            //     var_dump($post_id);
            // }
        
            // echo "<br><br><br>";
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
    
            $html = $this->generate_html_content($content_parts);
    
            $final_content = $this->append_freepik_image(
                $linker->generate_structured_links($html, $level,$post_id, $parent_id, $node['children'] ?? []),
                $content_parts['image_url'] ?? '',
                $content_parts['image'] ?? ''
            );


            // $final_content = $this->append_freepik_image([],
            //     $content_parts['image_url'] ?? '',
            //     $content_parts['image'] ?? ''
            // );
    
    
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


    

    

    private function generate_html_content($content_parts) {
        $html = '';

        // echo '<br>';echo '<br>';echo '<br>';
        // print_r($content_parts);
        // echo '<br>';echo '<br>';echo '<br>';


        if (!empty($content_parts['intro'])) {
            $html .= '<p>' . esc_html($content_parts['intro']) . '</p>';
        }

        if (!empty($content_parts['developments'])) {
            $linker = new CSB_Linker(); // ajouter en début de fonction si nécessaire

            foreach ($content_parts['developments'] as $dev) {
                $html .= '<h3>' . esc_html($dev['title']) . '</h3>';
                $html .= '<p>' . esc_html($dev['text']) . '</p>';

                if (!empty($node['children'])) {
                    foreach ($node['children'] as $child) {
                        if ($child['title'] === $dev['title']) {
                            $html .= $linker->get_clickbait_link($child);
                            break;
                        }
                    }
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
