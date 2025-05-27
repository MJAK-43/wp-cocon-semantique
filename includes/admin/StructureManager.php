<?php

if (!defined('ABSPATH')) exit;

class StructureManager {
    private array $map = [];
    private CSB_Publisher $publisher;

    public function __construct(CSB_Publisher $publisher) {
        $this->publisher = $publisher;
    }

    public function loadFromRawStructure(string $raw, string $keyword, ?string $forced_link = null): void {
        $parsed_lines = $this->parseStructureLines($raw);

        array_walk($parsed_lines, function (&$item) {
            $item['level'] += 1;
        });

        array_unshift($parsed_lines, [
            'index' => -1,
            'level' => 0,
            'title' => $this->capitalizeEachWord($keyword),
            'raw_indent' => 0
        ]);

        $this->map = $this->buildMapFromParsedLines($parsed_lines, $forced_link);
    }

    public function getMap(): array {
        return $this->map;
    }

    public function getNode(int $id): ?array {
        return $this->map[$id] ?? null;
    }

    public function addChild(int $parent_id): void {
        if (!isset($this->map[$parent_id])) return;
        $title = 'Nouveau sous-thème';
        $parent_level = $this->map[$parent_id]['level'] ?? 0;
        $child_level = $parent_level + 1;

        $entry = $this->createMapEntry($title, $parent_id, null, $child_level);
        $new_post_id = $entry['post_id'];
        $this->map[$new_post_id] = $entry;
        $this->map[$parent_id]['children_ids'][] = $new_post_id;
    }

    public function deleteNode(int $post_id): void {
        foreach ($this->map[$post_id]['children_ids'] as $child_id) {
            $this->deleteNode($child_id);
        }

        $parent_id = $this->map[$post_id]['parent_id'];
        if ($parent_id !== null && isset($this->map[$parent_id])) {
            $this->map[$parent_id]['children_ids'] = array_filter(
                $this->map[$parent_id]['children_ids'],
                fn($id) => $id !== $post_id
            );
        }

        unset($this->map[$post_id]);
    }

    public function updateFromPostData(array $posted_structure): void {
        foreach ($posted_structure as $post_id => $node_data) {
            if (isset($this->map[$post_id]) && isset($node_data['title'])) {
                $new_title = sanitize_text_field($node_data['title']);

                if ($this->map[$post_id]['title'] !== $new_title) {
                    $this->publisher->updatePostTitleAndSlug($post_id, $new_title);
                    if ($post_id > 0) {
                        $this->map[$post_id]['link'] = '/' . get_post_field('post_name', $post_id);
                    }
                }
                $this->map[$post_id]['title'] = $new_title;
            }
        }
    }

    public function rebuildFromRoot(int $post_id): void {
        $this->map = [];
        $this->rebuildCoconRecursive($post_id);
    }

    private function rebuildCoconRecursive(int $post_id): void {
        $title = get_the_title($post_id);
        $parent_id = get_post_meta($post_id, '_csb_parent_id', true);
        $level = get_post_meta($post_id, '_csb_level', true);

        if ($level === '' || $level === false) {
            update_post_meta($post_id, '_csb_level', 0);
            $level = 0;
        }
        if ($parent_id === '' || $parent_id === false) {
            update_post_meta($post_id, '_csb_parent_id', 0);
            $parent_id = 0;
        }

        $this->map[$post_id] = [
            'post_id' => $post_id,
            'title' => $title,
            'link' => wp_make_link_relative(get_permalink($post_id)),
            'parent_id' => intval($parent_id) ?: null,
            'children_ids' => [],
            'level' => intval($level)
        ];

        $children = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'draft'],
            'meta_key' => '_csb_parent_id',
            'meta_value' => $post_id,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);

        foreach ($children as $child) {
            $this->map[$post_id]['children_ids'][] = $child->ID;
            $this->rebuildCoconRecursive($child->ID);
        }
    }

    private function capitalizeEachWord($text): string {
        $text = strtolower($text);
        $text = preg_replace('#[/\\\\]+#', ' ', $text);
        return ucwords($text);
    }

    // ... (parseStructureLines, buildMapFromParsedLines, createMapEntry as dans ton exemple)
}
