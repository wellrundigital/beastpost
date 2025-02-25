<?php
/*
Plugin Name: BeastPost
Description: Uses OpenAI and Pexels APIs to generate a blog post from a subject input. Also provides a settings page for updating API keys.
Version: 1.2
Author: Wellrundigital | wellrundigital.com
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BeastPostPlugin {

    public function __construct() {
        // Add meta box to post/page editor.
        add_action('add_meta_boxes', array($this, 'add_beastpost_meta_box'));

        // Enqueue admin scripts.
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers for updating API keys and creating the post.
        add_action('wp_ajax_beastpost_update_key', array($this, 'ajax_update_key'));
        add_action('wp_ajax_beastpost_create_post', array($this, 'ajax_create_post'));

        // Add settings page and register settings.
        add_action('admin_menu', array($this, 'register_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    // Add the BeastPost meta box to the sidebar.
    public function add_beastpost_meta_box() {
        add_meta_box(
            'beastpost_metabox',          // ID.
            'BeastPost',                  // Title.
            array($this, 'render_meta_box'), // Callback.
            null,                         // Screen (all post types).
            'side',                       // Context.
            'high'                        // Priority.
        );
    }

    // Render the meta box HTML.
    public function render_meta_box($post) {
        // Retrieve stored API keys.
        $openai_key = get_option('beastpost_openai_key');
        $pexels_key = get_option('beastpost_pexels_key');
        ?>
        <div id="beastpost-container">
            <p>
                <!-- OpenAI API key indicator with pencil icon -->
                <span style="display:inline-block; padding:5px; border-radius:3px; background-color:<?php echo ($openai_key ? '#4CAF50' : '#F44336'); ?>; color:#fff;">
                    <?php echo ($openai_key ? 'OpenAI API key set' : 'OpenAI API key missing'); ?>
                </span>
                <a href="#" class="beastpost-edit-key" data-key-type="openai" title="Edit API key" style="margin-left:5px;">&#9998;</a>
            </p>
            <p>
                <!-- Pexels API key indicator with pencil icon -->
                <span style="display:inline-block; padding:5px; border-radius:3px; background-color:<?php echo ($pexels_key ? '#4CAF50' : '#F44336'); ?>; color:#fff;">
                    <?php echo ($pexels_key ? 'Pexels API key set' : 'Pexels API key missing'); ?>
                </span>
                <a href="#" class="beastpost-edit-key" data-key-type="pexels" title="Edit API key" style="margin-left:5px;">&#9998;</a>
            </p>
            <p>
                <!-- Main multi-row input field for subject -->
                <label for="beastpost-input">Article Subject:</label><br>
                <textarea id="beastpost-input" style="width:100%;" rows="5" placeholder="What is your article about?"></textarea>
            </p>
            <p>
                <!-- Create Post button -->
                <button type="button" id="beastpost-create-button" class="button button-primary">Create Post</button>
            </p>
        </div>
        <?php
    }

    // Enqueue the plugin’s JavaScript file on post editor pages.
    public function enqueue_scripts($hook) {
        if ( in_array($hook, array('post.php', 'post-new.php')) ) {
            wp_enqueue_script(
                'beastpost_script',
                plugins_url('beastpost.js', __FILE__),
                array('jquery'),
                '1.2',
                true
            );
            wp_localize_script('beastpost_script', 'beastpost', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('beastpost_nonce')
            ));
        }
    }

    // AJAX handler to update either API key.
    public function ajax_update_key() {
        check_ajax_referer('beastpost_nonce', 'nonce');

        $key_type  = sanitize_text_field($_POST['key_type']);
        $key_value = sanitize_text_field($_POST['key_value']);

        if ( $key_type === 'openai' ) {
            update_option('beastpost_openai_key', $key_value);
        } elseif ( $key_type === 'pexels' ) {
            update_option('beastpost_pexels_key', $key_value);
        } else {
            wp_send_json_error('Invalid key type.');
        }
        wp_send_json_success('Key updated.');
    }

    // AJAX handler to create the post.
    public function ajax_create_post() {
        check_ajax_referer('beastpost_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $subject = sanitize_textarea_field($_POST['subject']);

        // Retrieve saved API keys.
        $openai_key = get_option('beastpost_openai_key');
        $pexels_key = get_option('beastpost_pexels_key');

        if ( empty($openai_key) || empty($pexels_key) ) {
            wp_send_json_error('Both API keys must be set.');
        }

        // Construct the prompt for the OpenAI API.
        $prompt = "Write a clear, detailed and thorough blog post on the subject: " . $subject . ". Cover all corner cases, include official information and data, check official government resources for information where applicable and include citations. Format the output as a JSON object with keys: 'content' (the full WordPress formatted post content, including title, headers, lists, etc), 'seo_link' (an SEO optimized link), and 'image_description' (a three word description for a Pexels image).";

        // Call the OpenAI API.
        $openai_response = wp_remote_post('https://api.openai.com/v1/completions', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $openai_key,
            ),
            'body' => json_encode(array(
                'model'       => 'text-davinci-003',
                'prompt'      => $prompt,
                'max_tokens'  => 1024,
            )),
        ));

        if ( is_wp_error($openai_response) ) {
            wp_send_json_error('Error contacting OpenAI API: ' . $openai_response->get_error_message());
        }

        $openai_body = json_decode(wp_remote_retrieve_body($openai_response), true);
        if ( empty($openai_body) || !isset($openai_body['choices'][0]['text']) ) {
            wp_send_json_error('Invalid response from OpenAI API.');
        }
        $openai_text = trim($openai_body['choices'][0]['text']);

        // Assume the response is a JSON object.
        $response_data = json_decode($openai_text, true);
        if ( ! $response_data || !isset($response_data['content'], $response_data['seo_link'], $response_data['image_description']) ) {
            wp_send_json_error('Response format error. Full response: ' . print_r($openai_body, true));
        }

        $content           = $response_data['content'];
        $seo_link          = $response_data['seo_link'];
        $image_description = $response_data['image_description'];

        // Update the post content and the post slug (using the SEO link).
        wp_update_post(array(
            'ID'           => $post_id,
            'post_content' => $content,
            'post_name'    => sanitize_title($seo_link)
        ));

        // Query the Pexels API for an image.
        $pexels_response = wp_remote_get('https://api.pexels.com/v1/search?query=' . urlencode($image_description) . '&per_page=1', array(
            'headers' => array(
                'Authorization' => $pexels_key,
            ),
        ));

        if ( is_wp_error($pexels_response) ) {
            wp_send_json_error('Error contacting Pexels API: ' . $pexels_response->get_error_message());
        }

        $pexels_body = json_decode(wp_remote_retrieve_body($pexels_response), true);
        if ( empty($pexels_body) || !isset($pexels_body['photos'][0]) ) {
            wp_send_json_error('No image found from Pexels.');
        }

        $photo     = $pexels_body['photos'][0];
        $image_url = $photo['src']['small']; // Use the small size image.

        // Include required WordPress files for handling media.
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Download the image and attach it to the post.
        $featured_image_id = media_sideload_image($image_url, $post_id, null, 'id');
        if ( is_wp_error($featured_image_id) ) {
            wp_send_json_error('Error setting featured image: ' . $featured_image_id->get_error_message());
        }
        set_post_thumbnail($post_id, $featured_image_id);

        wp_send_json_success(array(
            'message'  => 'Post created successfully.',
            'seo_link' => $seo_link
        ));
    }

    // Register the plugin settings page.
    public function register_settings_page() {
        add_options_page(
            'BeastPost Settings',
            'BeastPost',
            'manage_options',
            'beastpost-settings',
            array($this, 'render_settings_page')
        );
    }

    // Render the settings page.
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>BeastPost Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('beastpost_settings_group');
                do_settings_sections('beastpost-settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td><input type="text" name="beastpost_openai_key" value="<?php echo esc_attr(get_option('beastpost_openai_key')); ?>" style="width:300px;" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Pexels API Key</th>
                        <td><input type="text" name="beastpost_pexels_key" value="<?php echo esc_attr(get_option('beastpost_pexels_key')); ?>" style="width:300px;" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // Register settings.
    public function register_settings() {
        register_setting('beastpost_settings_group', 'beastpost_openai_key');
        register_setting('beastpost_settings_group', 'beastpost_pexels_key');
    }
}

new BeastPostPlugin();
