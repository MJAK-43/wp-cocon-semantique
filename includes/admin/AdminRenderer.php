<?php

if (!defined('ABSPATH')) exit;

class AdminRenderer {
      

    private function renderStructureForm(
            array $mapIdPost,
            string $prefix = 'structure',
            int $level = 0,
            bool $use_existing_root = false,
            string $existing_root_url = ''
        ): void{
        echo '<form method="post">';
        echo '<fieldset class="csb-fieldset">';
        echo '<legend>Structure générée</legend>';

        // Affichage à partir de la racine (parent_id null)
        $this->renderStructureFields($mapIdPost,null, $prefix, 0, $use_existing_root==0);

        echo '</fieldset>';

        if ($use_existing_root) {
            echo '<input type="hidden" name="use_existing_root" value="1" />';
        }
        if (!empty($existing_root_url)) {
            echo '<input type="hidden" name="existing_root_url" value="' . esc_attr($existing_root_url) . '" />';
        }

        echo '<div class="csb-structure-actions">';
        echo '<button type="button" id="csb-generate-all" class="button button-primary">🚀 Tout générer</button> ';
        echo '<button type="submit" name="csb_stop_generation" class="button">🛑 Stopper la génération</button> ';
        echo '<button type="submit" name="csb_clear_structure" class="button button-danger" onclick="return confirm(\'Supprimer la structure et tous les brouillons ?\');">Supprimer le cocon</button>';
        echo '</div>';

        echo '</form>';
    }


    private function renderKeywordForm($keyword, $nb) {
        $use_existing_root = isset($_POST['use_existing_root']) ? 1 : 0;

        $original_url = isset($_POST['existing_root_url']) ? sanitize_text_field($_POST['existing_root_url']) : '';
        $existing_root_url = $this->sanitizeToRelativeUrl($original_url); // <-- Conversion ici
        
        $product = isset($_POST['csb_product']) ? sanitize_text_field($_POST['csb_product']) : '';
        $demographic = isset($_POST['csb_demographic']) ? sanitize_text_field($_POST['csb_demographic']) : '';

        
        echo '<form method="post">';
        echo '<table class="form-table">';

        // Champ mot-clé
        echo '<tr><th><label for="csb_keyword">Mots Clés principaux</label></th>';
        echo '<td><input type="text" id="csb_keyword" name="csb_keyword" value="' . esc_attr($keyword) . '" class="regular-text" required></td></tr>';

        // Champ nombre de niveaux
        echo '<tr><th><label for="csb_nb_nodes">Nombre de sous-niveaux</label></th>';
        echo '<td><input type="number" id="csb_nb_nodes" name="csb_nb_nodes" value="' . esc_attr($nb) . '" min="1" max="5" required class="regular-text" /></td></tr>';

        // Case à cocher
        echo '<tr><th><label for="use_existing_root">Utiliser un article racine existant</label></th>';
        echo '<td><input type="checkbox" id="use_existing_root" name="use_existing_root" value="1" ' . checked(1, $use_existing_root, false) . '></td></tr>';

        // Champ URL
        echo '<tr><th><label for="existing_root_url">URL de l’article racine</label></th>';
        echo '<td><input type="text" id="existing_root_url" name="existing_root_url" value="' . esc_attr($existing_root_url) . '" class="regular-text">';
        echo '<p class="description">Uniquement une URL relative (ex: /mon-article)</p>';

        if (!empty($original_url) && str_starts_with($original_url, 'http')) {
            echo '<p>❗ L’URL absolue a été automatiquement convertie en lien relatif';
        }

        echo '</td></tr>';

        // // Champ Activité
        // echo '<tr><th><label for="csb_activity">Activité</label></th>';
        // echo '<td><input type="text" id="csb_activity" name="csb_activity" value="' . esc_attr($activity) . '" class="regular-text">';
        // echo '<p class="description">Ex : artisan, e-commerçant, coach, etc.</p></td></tr>';

        // Champ Produit
        echo '<tr><th><label for="csb_product">Produit vendu</label></th>';
        echo '<td><input type="text" id="csb_product" name="csb_product" value="' . esc_attr($product) . '" class="regular-text">';
        echo '<p class="description">Ex : formations, bijoux, vêtements, accompagnement, etc.</p></td></tr>';


        // Champ Démographique
        echo '<tr><th><label for="csb_demographic">Démographique</label></th>';
        echo '<td><input type="text" id="csb_demographic" name="csb_demographic" value="' . esc_attr($demographic) . '" class="regular-text">';
        echo '<p class="description">Ex : parents, retraités, étudiants, etc.</p></td></tr>';

        echo '</table>';
        submit_button('Générer la structure', 'primary', 'submit');
        echo '</form>';
    }


    private function renderStructureFields($mapIdPost,$parent_id, string $prefix, int $level, bool $generation = true) {
        echo '<ul class="csb-structure-list level-' . $level . '" style="--level: ' . $level . ';">';

        foreach ($mapIdPost as $id => $node) {
            $isChild = ($node['parent_id'] === $parent_id);
            $isLeaf = empty($node['children_ids']);
            $isVirtual = $id < 0;

            if ($isChild && !$isLeaf && !$isVirtual) {
                $node_prefix = $prefix . "[" . strval($id) . "]";

                echo '<li class="csb-node-item">';
                echo '<div class="csb-node-controls">';
                echo '<span class="csb-node-indent">-</span>';
                
                $readonly = $generation ? '' : 'readonly';
                echo '<input type="text" name="' . esc_attr($node_prefix . '[title]') . '" value="' . esc_attr($node['title']) . '" class="regular-text" ' . $readonly . ' required />';

                if ($generation) {
                    echo '<button type="button" class="button csb-generate-node" data-post-id="' . esc_attr($id) . '">⚙️ Générer </button>';
                }

                echo '<span class="csb-node-status" data-post-id="' . esc_attr($id) . '"></span>';
                echo '</div>';

                // Récursion sur les enfants
                $this->renderStructureFields($id, $prefix, $level + 1, true);
                echo '</li>';
            }
        }

        echo '</ul>';
    }


    private function renderLoadedStructure($mapIdPostLoaded,?int $parent_id = null, int $level = 0): void {
        if (empty($mapIdPostLoaded)) {
            echo '<p>Aucun cocon chargé.</p>';
            return;
        }
    
        echo '<ul style="padding-left: ' . (20 * $level) . 'px;">';
    
        foreach ($mapIdPostLoaded as $id => $node) {
            if ($node['parent_id'] !== $parent_id) continue;
    
            $title = esc_html($node['title']);
            $link = esc_url($node['link']);
    
            echo "<li><a href=\"$link\" target=\"_blank\">🔗 $title</a>";
    
            $this->renderLoadedStructure($mapIdPostLoaded,$id, $level + 1); // récursif
    
            echo '</li>';
        }
    
        echo '</ul>';
    }
}

