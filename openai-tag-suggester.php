<?php
/*
Plugin Name: OpenAI Tag Suggester
Description: Suggests tags for posts using the OpenAI API.
Version: 1.0
Author: Alex Chapin
*/

// Add settings page
add_action('admin_menu', 'openai_tag_suggester_menu');
function openai_tag_suggester_menu() {
    add_options_page('OpenAI Tag Suggester Settings', 'OpenAI Tag Suggester', 'manage_options', 'openai-tag-suggester', 'openai_tag_suggester_settings_page');
}

function openai_tag_suggester_settings_page() {
    ?>
    <div class="wrap">
        <h1>OpenAI Tag Suggester Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('openai_tag_suggester_options');
            do_settings_sections('openai_tag_suggester');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'openai_tag_suggester_settings');
function openai_tag_suggester_settings() {
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_api_key');
    add_settings_section('openai_tag_suggester_main', 'Main Settings', null, 'openai_tag_suggester');
    add_settings_field('openai_tag_suggester_api_key', 'OpenAI API Key', 'openai_tag_suggester_api_key_field', 'openai_tag_suggester', 'openai_tag_suggester_main');
}

function openai_tag_suggester_api_key_field() {
    $api_key = get_option('openai_tag_suggester_api_key');
    echo "<input type='text' name='openai_tag_suggester_api_key' value='$api_key' />";
}

// Hook into post save
// add_action('save_post', 'openai_tag_suggester_generate_tags', 10, 2);

function openai_tag_suggester_generate_tags($post_id, $post) {
    if ($post->post_type != 'post' || wp_is_post_revision($post_id)) {
        error_log('Invalid post type or revision.');
        return;
    }

    $api_key = get_option('openai_tag_suggester_api_key');
    if (!$api_key) {
        error_log('API key not set.');
        return;
    }

    $post_content = $post->post_content;
    $tags = openai_tag_suggester_get_tags($post_content, $api_key);

    if ($tags) {
        wp_set_post_tags($post_id, $tags, true);
    } else {
        error_log('No tag suggestions available.');
    }
}

function openai_tag_suggester_get_tags($content, $api_key) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $cleaned_content = wp_strip_all_tags($content);
    $cleaned_content = substr($cleaned_content, 0, 2000);
    
    $data = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => array(
            array(
                'role' => 'system',
                'content' => 'You are a university communications specialist. You are tasked with suggesting tags for faculty profiles that highlight their research interests. These tags should capture each faculty persons unique research interests while at the same time being relevant to the university\'s mission and goals and connect with other faculty who have similar research interests. Respond only with comma-separated tags, no other text.'
            ),
            array(
                'role' => 'user',
                'content' => "Suggest 3-15 tags for the following faculty profile: $cleaned_content. There should NOT be any tag suggestions for places, for position titles, or for names of people."
            )
        ),
        'temperature' => 0.7,
        'max_tokens' => 100
    );

    $args = array(
        'body' => json_encode($data),
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ),
    );

    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        error_log('OpenAI API request failed: ' . $response->get_error_message());
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    error_log('OpenAI API response: ' . print_r($result, true));

    if (isset($result['choices'][0]['message']['content'])) {
        $tags = explode(',', $result['choices'][0]['message']['content']);
        return array_map('trim', $tags);
    }

    return [];
}

// Add meta box to edit post page
add_action('add_meta_boxes', 'openai_tag_suggester_add_meta_box');
function openai_tag_suggester_add_meta_box() {
    add_meta_box(
        'openai_tag_suggester_meta_box',
        'Tag Suggestions',
        'openai_tag_suggester_meta_box_callback',
        'post',
        'side'
    );
}

function openai_tag_suggester_meta_box_callback($post) {
    echo '<div id="openai_tag_suggester_meta_box">';
    echo '<button id="openai_tag_suggester_button" class="button button-primary">Generate Tag Suggestions</button>';
    echo '<div id="openai_tag_suggester_results"></div>';
    echo '</div>';
}

/**
 * Inject our Tag Suggester UI into the Tags meta box
 * The 'post_tag_add_form_fields' hook adds content at the top of the Tags meta box
 */
add_action('post_tag_add_form_fields', 'openai_tag_suggester_inject_ui', 1);
function openai_tag_suggester_inject_ui() {
    // Create a container for our UI with loading state
    echo '<div id="openai_tag_suggester_container" style="margin-bottom: 15px;">';
    
    // Add the "Generate Tag Suggestions" button
    echo '<button id="openai_tag_suggester_button" class="button button-primary">Regenerate Tag Suggestions</button>';
    
    // Add a container for displaying results with initial loading message
    echo '<div id="openai_tag_suggester_results">Loading tag suggestions...</div>';
    
    echo '</div>';
}

/**
 * Enqueue necessary CSS and JavaScript files for the admin interface
 */
add_action('admin_enqueue_scripts', 'openai_tag_suggester_enqueue_admin_scripts');
function openai_tag_suggester_enqueue_admin_scripts($hook) {
    // Only load on post edit page
    if ($hook != 'post.php' && $hook != 'post-new.php') {
        return;
    }

    // Enqueue our custom CSS file
    wp_enqueue_style(
        'openai-tag-suggester-admin', 
        plugin_dir_url(__FILE__) . 'openai-tag-suggester-admin.css'
    );
    
    // Enqueue our custom JavaScript file
    wp_enqueue_script(
        'openai-tag-suggester-admin', 
        plugin_dir_url(__FILE__) . 'openai-tag-suggester-admin.js', 
        array('jquery'), 
        null, 
        true  // Load in footer
    );
    
    // Pass PHP variables to JavaScript
    wp_localize_script(
        'openai-tag-suggester-admin', 
        'openaiTagSuggester', 
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id' => get_the_ID(),
            'nonce' => wp_create_nonce('openai_tag_suggester_nonce'),
            'autoload' => true  // New flag to indicate we want automatic loading
        )
    );
}

// Handle AJAX request to generate tag suggestions
add_action('wp_ajax_openai_tag_suggester_generate_tags', 'openai_tag_suggester_generate_tags_ajax');
function openai_tag_suggester_generate_tags_ajax() {
    check_ajax_referer('openai_tag_suggester_nonce', 'nonce');

    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);

    if ($post->post_type != 'post' || wp_is_post_revision($post_id)) {
        error_log('Invalid post type or revision.');
        wp_send_json_error('Invalid post type or revision.');
    }

    $api_key = get_option('openai_tag_suggester_api_key');
    if (!$api_key) {
        error_log('API key not set.');
        wp_send_json_error('API key not set.');
    }

    $post_content = $post->post_content;
    $tags = openai_tag_suggester_get_tags($post_content, $api_key);

    if ($tags) {
        wp_send_json_success($tags);
    } else {
        error_log('No tag suggestions available.');
        wp_send_json_error('No tag suggestions available.');
    }
}

// Add this with your other action hooks
add_action('save_post', 'openai_tag_suggester_save_tags', 10, 2);

function openai_tag_suggester_save_tags($post_id, $post) {
    // Skip autosaves and revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    
    // Check if this is a valid post type
    if ($post->post_type != 'post') return;
    
    // Get tags from the input field
    if (isset($_POST['tax_input']['post_tag'])) {
        $tags = sanitize_text_field($_POST['tax_input']['post_tag']);
        $tag_array = array_map('trim', explode(',', $tags));
        
        // Set the tags
        wp_set_post_tags($post_id, $tag_array, false);
    }
}

// Add new AJAX handler for saving tags
add_action('wp_ajax_openai_tag_suggester_save_tags', 'openai_tag_suggester_save_tags_ajax');
function openai_tag_suggester_save_tags_ajax() {
    // Verify nonce
    check_ajax_referer('openai_tag_suggester_nonce', 'nonce');

    // Get post ID and tags
    $post_id = intval($_POST['post_id']);
    $tags = $_POST['tags'];

    if (!$tags || !is_array($tags)) {
        wp_send_json_error('No tags provided');
        return;
    }

    // Sanitize tags
    $tags = array_map('sanitize_text_field', $tags);

    // Get existing tags
    $existing_tags = wp_get_post_tags($post_id, array('fields' => 'names'));
    
    // Combine existing and new tags, removing duplicates
    $all_tags = array_unique(array_merge($existing_tags, $tags));

    // Save tags
    $result = wp_set_post_tags($post_id, $all_tags);

    if ($result) {
        wp_send_json_success('Tags saved successfully');
    } else {
        wp_send_json_error('Failed to save tags');
    }
}
?>