<?php
if (!defined('ABSPATH')) exit;

class CSB_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_submenu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_submenu() {
        add_submenu_page(
            'csb_admin',
            'Configuration Cocon Sémantique',
            'Configuration',
            'manage_options',
            'csb_settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('csb_settings_group', 'csb_openai_api_key');
        register_setting('csb_settings_group', 'csb_model');
        register_setting('csb_settings_group', 'csb_temperature');
        register_setting('csb_settings_group', 'csb_writing_style');

        // Option : clé API Freepik
        register_setting('csb_settings_group', 'csb_freepik_api_key');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>🔐 Configuration de l'API OpenAI & Freepik</h1>
            <form method="post" action="options.php">
                <?php settings_fields('csb_settings_group'); ?>
                <?php do_settings_sections('csb_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="csb_openai_api_key">Clé API OpenAI</label></th>
                        <td>
                            <input type="password" name="csb_openai_api_key" id="csb_openai_api_key" class="regular-text" value="<?php echo esc_attr(get_option('csb_openai_api_key')); ?>" />
                            <p class="description">Collez ici votre clé OpenAI commençant par <code>sk-</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="csb_model">Modèle GPT</label></th>
                        <td>
                            <select name="csb_model" id="csb_model">
                                <option value="gpt-3.5-turbo" <?php selected(get_option('csb_model'), 'gpt-3.5-turbo'); ?>>gpt-3.5-turbo</option>
                                <option value="gpt-4o" <?php selected(get_option('csb_model'), 'gpt-4o'); ?>>gpt-4o</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="csb_temperature">Température</label></th>
                        <td>
                            <input type="number" name="csb_temperature" id="csb_temperature" step="0.1" min="0" max="1" value="<?php echo esc_attr(get_option('csb_temperature', 0.7)); ?>" />
                            <p class="description">Entre 0 (précis) et 1 (créatif).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="csb_writing_style">Style de rédaction</label></th>
                        <td>
                            <input type="text" name="csb_writing_style" id="csb_writing_style" class="regular-text" value="<?php echo esc_attr(get_option('csb_writing_style', 'SEO')); ?>" />
                            <p class="description">Ex : SEO, académique, technique, storytelling…</p>
                        </td>
                    </tr>

                    <!-- ✅ Champ pour l'API Freepik -->
                    <tr>
                        <th scope="row"><label for="csb_freepik_api_key">Clé API Freepik</label></th>
                        <td>
                            <input type="password" name="csb_freepik_api_key" id="csb_freepik_api_key" class="regular-text" value="<?php echo esc_attr(get_option('csb_freepik_api_key')); ?>" />
                            <p class="description">Collez ici votre clé API Freepik si vous en avez une.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('💾 Enregistrer les paramètres'); ?>
            </form>
        </div>
        <?php
    }
}
