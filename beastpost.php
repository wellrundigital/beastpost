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

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'beastpost_add_settings_link'));
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
        
        // Get last 4 characters of keys if they exist
        $openai_last_four = $openai_key ? ' (...' . substr($openai_key, -4) . ')' : '';
        $pexels_last_four = $pexels_key ? ' (...' . substr($pexels_key, -4) . ')' : '';
        ?>
        <div id="beastpost-container">
            <p>
                <!-- OpenAI API key indicator with pencil icon -->
                <span style="display:inline-block; padding:5px; border-radius:3px; background-color:<?php echo ($openai_key ? '#4CAF50' : '#F44336'); ?>; color:#fff;">
                    <?php echo ($openai_key ? 'OpenAI API key set' . $openai_last_four : 'OpenAI API key missing'); ?>
                </span>
                <a href="#" class="beastpost-edit-key" data-key-type="openai" title="Edit API key" style="margin-left:5px;">&#9998;</a>
            </p>
            <p>
                <!-- Pexels API key indicator with pencil icon -->
                <span style="display:inline-block; padding:5px; border-radius:3px; background-color:<?php echo ($pexels_key ? '#4CAF50' : '#F44336'); ?>; color:#fff;">
                    <?php echo ($pexels_key ? 'Pexels API key set' . $pexels_last_four : 'Pexels API key missing'); ?>
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

    // Enqueue the plugin's JavaScript file on post editor pages.
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

        // Construct the system and user messages for the OpenAI API
        $messages = array(
            array(
                'role' => 'user',
                'content' => "Write a detailed blog post about: " . $subject . ". Follow these requirements:\n\n" .
                            "1. Use Gutenberg blocks format for ALL content\n" .
                            "2. Start with a title block: <!-- wp:post-title /-->\n" .
                            "3. Follow with an intro paragraph block: <!-- wp:paragraph -->\n" .
                            "4. Use appropriate blocks for all content (paragraphs, lists, quotes, etc)\n" .
                            "5. Include proper HTML formatting within blocks (<strong>, <em>, etc)\n" .
                            "6. Add citations where applicable\n\n" .
                            "Format the response as a JSON object with these properties:\n" .
                            "- title: The post title (without any HTML tags)\n" .
                            "- content: The full WordPress Gutenberg blocks formatted blog post\n" .
                            "- seo_link: An SEO optimized URL for the post\n" .
                            "- image_description: A three-word description for a relevant featured image"
            )
        );

        // Call the OpenAI API using the chat completions endpoint
        $openai_response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $openai_key,
            ),
            'timeout' => 60,
            'body' => json_encode(array(
                'model' => 'gpt-4o-2024-08-06',
                'messages' => $messages,
                'response_format' => array(
                    "type" => "json_schema",
                    "json_schema" => array(
                        "name" => "blog_post",
                        "schema" => array(
                            "type" => "object",
                            "properties" => array(
                                "content" => array(
                                    "type" => "string",
                                    "description" => "The full WordPress formatted blog post content, including title, headers, lists, and text."
                                ),
                                "seo_link" => array(
                                    "type" => "string",
                                    "description" => "An SEO optimized URL link for the blog post."
                                ),
                                "image_description" => array(
                                    "type" => "string",
                                    "description" => "A three-word description suitable for a Pexels image related to the blog content."
                                )
                            ),
                            "required" => array("content", "seo_link", "image_description"),
                            "additionalProperties" => false
                        )
                    )
                    )
            )),
        ));

        if ( is_wp_error($openai_response) ) {
            wp_send_json_error('Error contacting OpenAI API: ' . $openai_response->get_error_message());
        }

        $openai_body = json_decode(wp_remote_retrieve_body($openai_response), true);
        if ( empty($openai_body) || !isset($openai_body['choices'][0]['message']['content']) ) {
            $error_message = 'Invalid response from OpenAI API. Full response: ' . wp_json_encode($openai_body);
            if (isset($openai_body['error'])) {
                $error_message .= "\nError details: " . wp_json_encode($openai_body['error']);
            }
            wp_send_json_error($error_message);
        }
        
        $openai_text = trim($openai_body['choices'][0]['message']['content']);

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
            'title' => wp_strip_all_tags($response_data['title']),
            'content' => $content,
            'seo_link' => $seo_link,
            'image_description' => $image_description
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

    // Add settings link to plugins page
    public function beastpost_add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=beastpost-settings') . '">' . __('Settings', 'beastpost') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

new BeastPostPlugin();
