<?php
if (!defined('ABSPATH')) exit;

class CSB_Linker {
    

    
    /**
     * Génère les sections de liens internes selon le niveau de l'article.
     */
    public function generate_structured_links(array $map, int $post_id): string {
        $content = '';
    
        $idPostRoot = $this->get_root_from_map($map);

        if ($post_id !== $idPostRoot) {
            $link = esc_url($map[$idPostRoot]['link']);
            $title = esc_html($map[$idPostRoot]['title']);
    
            $content .= "<div class='csb-links'><h3> Tout savoir sur ce sujet :</h3>";
            $content .= "<ul><li><a href='$link' target='_blank'>$title</a></li></ul></div>";
        }
         // Liens vers les frères et sœurs
        $siblings = $this->get_siblings_from_map($post_id, $map);
        if (!empty($siblings)) {
            $sibling_links = [];
            foreach ($siblings as $sibling) {
                if (!empty($sibling['link']) && !empty($sibling['title'])) {
                    $sibling_links[] = "<a href='" . esc_url($sibling['link']) . "'>" . esc_html($sibling['title']) . "</a>";
                }
            }

            if (!empty($sibling_links)) {
                $content .= "<div class='csb-links'><h3>Articles liés :</h3>";
                $content .= "<ul><li>" . implode("</li><li>", $sibling_links) . "</li></ul></div>";
            }
        }
    
        return $content;
    }
    
    

    /**
     * Récupère les frères et sœurs du nœud dans l’arbre.
     */
    public function get_siblings_from_map(int $post_id, array $map): array {
        if (!isset($map[$post_id])) return [];
    
        $parent_id = $map[$post_id]['parent_id'] ?? null;
        $siblings = [];
    
        foreach ($map as $id => $node) {
            if (
                $id !== $post_id && // exclut lui-même
                ($node['parent_id'] ?? null) === $parent_id
            ) {
                $siblings[] = $node;
            }
        }
    
        return $siblings;
    }
    
    
    
    

    /**
     * Récupère le nœud racine à partir du slug dans l’arbre.
     */
    public function get_root_from_map(array $map){
        $post_id=array_key_first($map);
        return $post_id;
    }    
}
