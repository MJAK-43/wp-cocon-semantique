<?php
if (!defined('ABSPATH')) exit;

class CSB_Publisher {
    private int $publishedCount = 0;
    public function getPublishedCount(): int {
        return $this->publishedCount;
    }


    public function registerAllPost(array &$tree) {
        foreach ($tree as $slug => &$node) {
            $post_id = $this->createPostDraft($node['title']);
            $node['post_id']=$post_id;
            if (!empty($node['children'])) {
                $this->registerAllPost($node['children']);
            }
        }
    }

    

    public function fill_and_publish_content(int $post_id, string $html_content): void {
        $this->publishedCount++; 
        $updated = wp_update_post([
            'ID'           => $post_id,
            'post_content' => $html_content,
            'post_status'  => 'publish', 
        ], true);
    }
    

    private function createPostDraft($title) {
        /*$slug = $this->generate_unique_slug($title);*/
        $post= wp_insert_post([
            'post_title'   => $title,
            /*'post_name'    => $slug,*/
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            //'post_parent'  => $parent_id,
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
                    // if($dev['link']=='')
                    //     print_r("Lien vide");
                    // else
                    //     print_r($dev['link']);
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

    public function set_featured_image(int $post_id, string $image_url): void {
        if (empty($image_url) || str_starts_with($image_url, '❌')) {
            error_log("❌ Image invalide pour mise en avant : $image_url");
            return;
        }
    
        // Téléchargement de l'image
        $tmp = download_url($image_url);
    
        if (is_wp_error($tmp)) {
            error_log("❌ Erreur téléchargement image : " . $tmp->get_error_message());
            return;
        }
    
        // Prépare un tableau pour insérer le fichier dans la médiathèque
        $file_array = [
            'name'     => basename($image_url),
            'tmp_name' => $tmp,
        ];
    
        // Si media_handle_sideload() échoue, nettoyage manuel
        $attachment_id = media_handle_sideload($file_array, $post_id);
    
        if (is_wp_error($attachment_id)) {
            @unlink($tmp); // Nettoyer le fichier temporaire
            error_log("❌ Erreur media_handle_sideload : " . $attachment_id->get_error_message());
            return;
        }
    
        // Définir comme image mise en avant
        set_post_thumbnail($post_id, $attachment_id);
    }
    
} 
