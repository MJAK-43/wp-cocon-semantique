<?php

if (!defined('ABSPATH')) exit;

class NodeProcessor {
    private GeneratorInterface $generator;
    private CSB_Publisher $publisher;
    private CSB_Linker $linker;
    private bool $debugContent;
    private bool $debugImage;

    public function __construct(
        GeneratorInterface $generator,
        CSB_Publisher $publisher,
        CSB_Linker $linker,
        bool $debugContent = false,
        bool $debugImage = false
    ) {
        $this->generator = $generator;
        $this->publisher = $publisher;
        $this->linker = $linker;
        $this->debugContent = $debugContent;
        $this->debugImage = $debugImage;
    }


    private function toBulletArchitecture(array $map, int $current_id = null, int $indent = 0): string {
        $out = '';

        foreach ($map as $id => $node) {
            if ($node['parent_id'] === $current_id) {
                $out .= str_repeat('    ', $indent) . "- {$node['title']}\n";

                if (!empty($node['children_ids'])) {
                    $out .= $this->toBulletArchitecture($map, $id, $indent + 1);
                }
            }
        }
        return $out;
    }


    private function slugify(string $text): string {
        // 1. Translittération : convertit les accents et caractères spéciaux
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        // 2. Mise en minuscule
        $text = strtolower($text);

        // 3. Remplace les caractères non alphanumériques par des tirets
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // 4. Supprime les tirets en début ou fin
        return trim($text, '-');
    }


    private function processNode(
        int $post_id,
        array &$map,
        int $nb,
        string $keyword,
        ?string $product = null,
        ?string $demographic = null): void {
        $maxTime = (int) ini_get('max_execution_time');

        if (isset($map[$post_id])) {
            try {
                // Construction du PromptContext avec uniquement les champs utiles
                $context_data = ['keyword' => $keyword];
                if (!empty($product)) {
                    $context_data['product'] = $product;
                }
                if (!empty($demographic)) {
                    $context_data['demographic'] = $demographic;
                }
                $context = new PromptContext($context_data);

                // Choix du mode de génération
                if ($maxTime < self::$minExecutionTimeForSafe) {
                    $result = $this->processNodeFast($post_id, $map, $nb, $keyword, $context);
                } else {
                    $result = $this->processNodeSafe($post_id, $map, $nb, $keyword, $context);
                }

                // Génération de l’image
                $title = $map[$post_id]['title'];
                if (!$this->debugModImage) {
                    $image_url = $this->generator->generateImage($title, $keyword, $context, $this->debugModImage);
                    $this->publisher->setFeaturedImage($post_id, $image_url);
                }

                // Ajout des liens internes
                $links = $this->linker->generateStructuredLinks($map, $post_id);
                $content = $result . $links;

                $this->publisher->fillAndPublishContent($post_id, $content);

            } catch (\Throwable $e) {
                //error_log("Erreur dans processNode pour post_id $post_id : " . $e->getMessage());
            }
        }
    }


    private function processNodeSafe(int $post_id, array &$map, int $nb, string $keyword,PromptContext $context): string {
        $node = $map[$post_id];
        $title = $node['title'];
        $slug = get_post_field('post_name', $post_id);
        $structure = $this->toBulletArchitecture($map);

        // 🔸 Introduction
        $intro = $this->generator->generateIntro($title, $structure, $context, $this->debugModContent);
        $intro = "<div id='csb-intro-$slug' class='csb-content csb-intro'>$intro</div>";

        // 🔸 Développements
        $developments_html = '';

        if (!empty($node['children_ids'])) {
            foreach ($node['children_ids'] as $child_id) {
                if (isset($map[$child_id])) {
                    $child = $map[$child_id];
                    $child_title = $child['title'];
                    $child_slug = $this->slugify($child_title);
                    $dev = $this->generator->generateDevelopment($child_title, $structure, $context,$this->debugModContent);
                    $block_id = ($child_id < 0) ? "csb-leaf-$child_slug" : "csb-development-$child_slug";
                    $dev_html = "<div id='$block_id' class='csb-content csb-development'>$dev</div>";

                    if ($child_id >= 0) {
                        $link = '<p>Pour en savoir plus, découvrez notre article sur <a href="' . esc_url($child['link']) . '">' . esc_html($child_title) . '</a>.</p>';
                        $dev_html .= $link;
                    }

                    $developments_html .= $dev_html;
                }
            }
        }

        // Conclusion
        $conclusion = $this->generator->generateConclusion($title, $structure,  $context,$this->debugModContent);
        $conclusion = "<div id='csb-conclusion-$slug' class='csb-content csb-conclusion'>$conclusion</div>";
        return $intro . $developments_html . $conclusion . '<!-- Mode sécurisé -->';
    }


    private function processNodeFast(int $post_id, array &$map, int $nb, string $keyword,PromptContext $context): string {        
        $node = $map[$post_id];
        $title = $node['title'];
        $structure = $this->toBulletArchitecture($map);
        $subparts = [];

        $isLeaf = $this->isLeafNode($post_id, $map);

        if ($isLeaf) {
            // Feuille réelle : récupérer les titres des enfants virtuels (niveau 3)
            foreach ($node['children_ids'] as $child_id) {
                if (isset($map[$child_id]) && $map[$child_id]['post_id'] < 0) {
                    $subparts[$map[$child_id]['title']] = null;
                }
            }
        } 
        else {
            // Article non-feuille : récupérer les titres + liens des enfants réels
            foreach ($node['children_ids'] as $child_id) {
                if (isset($map[$child_id]) && $map[$child_id]['post_id'] >= 0) {
                    $child = $map[$child_id];
                    $subparts[$child['title']] = $child['link'];
                }
            }
        }

        // Génération du contenu HTML 
        $content = $this->generator->generateFullContent(
            keyword: $keyword,
            title: $title,
            structure: $structure,
            subparts: $subparts,
            context: $context,
            test: $this->debugModContent
        );
        return '<article class="article-csb">' . $content . '<!-- Mode rapide -->' . '</article>';

    
    }


    private function isLeafNode(int $node_id, array $map): bool {
        foreach ($map[$node_id]['children_ids'] ?? [] as $child_id) {
            if (isset($map[$child_id]) && $map[$child_id]['post_id'] >= 0) {
                return false; // Il a au moins un enfant réel
            }
        }

        // Aucun enfant réel trouvé ⇒ c’est une feuille
        return true;
    }
}
