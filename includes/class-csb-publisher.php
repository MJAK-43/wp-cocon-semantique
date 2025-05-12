<?php
if (!defined('ABSPATH')) exit;

class CSB_Publisher {
    private int $publishedCount = 0;
    public function getPublishedCount(): int {
        return $this->publishedCount;
    }

    
    public function updatePostTitleAndSlug(int $post_id, string $new_title): void {
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $new_title,
            'post_name'  => sanitize_title($new_title),
        ]);
    }
    

    public function fill_and_publish_content(int $post_id, string $html_content): void {
        $this->publishedCount++; 
        $updated = wp_update_post([
            'ID'           => $post_id,
            'post_content' => $html_content,
            'post_status'  => 'publish', 
        ], true);
    }
    

    public function createPostDraft($title) {
        /*$slug = $this->generate_unique_slug($title);*/
        $post= wp_insert_post([
            'post_title'   => $title,
            /*'post_name'    => $slug,*/
            'post_content' => '',
            'post_status'  => 'draft',
            'post_type'    => 'post',
            //'post_parent'  => $parent_id,
        ]);

        // echo '<br>';echo '<br>';
        // print_r($post);
        // echo '<br>';echo '<br>';
        //update_post_meta($post_id, '_csb_generated', 1); 

        return $post;
    }

    

    public function storeMeta(int $post_id, int $level, ?int $parent_id = null): void {
        update_post_meta($post_id, '_csb_level', $level);
        update_post_meta($post_id, '_csb_parent_id', $parent_id ?? 0);
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


    public function getAllRootNodesFromMeta(): array {
        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'meta_key'       => '_csb_level',
            'meta_value'     => '0',
            'posts_per_page' => -1,
        ];
    
        $query = new WP_Query($args);
        $roots = [];
    
        foreach ($query->posts as $post) {
            $roots[] = [
                'post_id' => $post->ID,
                'title'   => get_the_title($post->ID),
                'link'    => wp_make_link_relative(get_permalink($post->ID)),
            ];
        }
    
        return $roots;
    }
    
} 
