<?php
if (!defined('ABSPATH')) exit;

class CSB_Publisher {
    private int $publishedCount = 0;
    public function getPublishedCount(): int {
        return $this->publishedCount;
    }

    
    public function updatePostTitleAndSlug(int $post_id, string $new_title): void {
        //error_log("updatePostTitleAndSlug $post_id , $new_title");

        wp_update_post([
            'ID'         => $post_id,
            'post_title' => $new_title,
            'post_name'  => sanitize_title($new_title),
        ]);
    }
    

    public function fillAndPublishContent(int $post_id, string $html_content): void {
        $this->publishedCount++;
        //error_log("Parametre fillAndPublishContent : $html_content");
        $updated = wp_update_post([
            'ID'           => $post_id,
            'post_content' => $html_content,
            'post_status'  => 'publish', 
        ], true);
    }
    

    public function createPostDraft(string $title, int $level = 0, ?int $parent_id = null){
        $slug = $this->generateUniqueSlug($title);
        $post= wp_insert_post([
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => '',
            'post_status'  => 'draft',
            'post_type'    => 'post',
            //'post_parent'  => $parent_id,
        ]);

        // echo '<br>';echo '<br>';
        // print_r($post);
        // echo '<br>';echo '<br>';
        if (!is_wp_error($post)) {
            $this->markAsGenerated($post);
            $this->storeMeta($post, $level, $parent_id);

        }

        return $post;
    }

    public function markAsGenerated(int $post_id): void {
        update_post_meta($post_id, '_csb_generated', 1);
    }

    
    public function markAsRoot(int $post_id): void {
        $this->storeMeta($post_id, 0, 0);
        $this->markAsGenerated($post_id);
    }


    public function deletePost(int $post_id): void {
        if ($post_id > 0 && get_post($post_id)) {
           wp_delete_post($post_id, true); // true = force delete
        }
    }


    private function generateUniqueSlug(string $title): string {
        $base_slug = sanitize_title($title);
        $slug = $base_slug;
        $i = 1;

        while ($this->slugExists($slug)) {
            $slug = $base_slug . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug): bool {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_status != 'trash' LIMIT 1",
            $slug
        );

        return (bool) $wpdb->get_var($query);
    }

    

    public function storeMeta(int $post_id, int $level, ?int $parent_id = null): void {
        update_post_meta($post_id, '_csb_level', $level);
        update_post_meta($post_id, '_csb_parent_id', $parent_id ?? 0);
    }



    public function setFeaturedImage(int $post_id, string $image_url): void {
        $attachment_id = null;

        if (empty($image_url) || str_starts_with($image_url, '❌')) {
            error_log("❌ Image invalide pour mise en avant : $image_url");
        } 
        else {
            $site_url = get_site_url();

            // Si l'image est locale (dans WordPress ou dans le plugin)
            if (!str_starts_with($image_url, 'http') || str_starts_with($image_url, $site_url)) {
                $attachment_id = attachment_url_to_postid($image_url);
            } 
            else {
                // Sinon, on tente de la télécharger (image distante)
                $tmp = download_url($image_url);

                if (is_wp_error($tmp)) {
                    error_log("❌ Erreur téléchargement image : " . $tmp->get_error_message());
                } 
                else {
                    $file_array = [
                        'name'     => basename($image_url),
                        'tmp_name' => $tmp,
                    ];

                    $attachment = media_handle_sideload($file_array, $post_id);

                    if (is_wp_error($attachment)) {
                        @unlink($tmp);
                        error_log("❌ Erreur media_handle_sideload : " . $attachment->get_error_message());
                    } 
                    else {
                        $attachment_id = $attachment;
                    }
                }
            }
        }

        // ✅ Un seul return, on définit la miniature si tout s'est bien passé
        if (!empty($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
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
