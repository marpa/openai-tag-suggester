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
    // Debug output
    echo '<div class="debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">';
    echo '<h3>Debug Information</h3>';
    echo '<pre>';
    echo 'Enabled Taxonomies: ' . print_r(get_option('openai_tag_suggester_enabled_taxonomies'), true) . "\n";
    echo 'Available Taxonomies: ' . print_r(openai_tag_suggester_get_available_taxonomies(), true) . "\n";
    echo '</pre>';
    echo '</div>';
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
    // API Key setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_api_key');
    
    // System Role setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_system_role', array(
        'default' => 'You are a university communications specialist. You are tasked with suggesting tags for faculty profiles that highlight their research interests.'
    ));
    
    // User Role setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_user_role', array(
        'default' => 'Suggest 3-15 tags for the following faculty profile. There should NOT be any tag suggestions for places, for position titles, or for names of people.'
    ));

    // Enabled Taxonomies setting
    register_setting(
        'openai_tag_suggester_options', 
        'openai_tag_suggester_enabled_taxonomies',
        array(
            'type' => 'array',
            'default' => array('post_tag'),
            'sanitize_callback' => 'openai_tag_suggester_sanitize_taxonomies'
        )
    );

    // Add settings sections
    add_settings_section(
        'openai_tag_suggester_main',
        'Main Settings',
        null,
        'openai_tag_suggester'
    );

    add_settings_section(
        'openai_tag_suggester_taxonomies',
        'Taxonomy Settings',
        'openai_tag_suggester_taxonomies_section_callback',
        'openai_tag_suggester'
    );

    // Add settings fields
    add_settings_field(
        'openai_tag_suggester_api_key',
        'OpenAI API Key',
        'openai_tag_suggester_api_key_field',
        'openai_tag_suggester',
        'openai_tag_suggester_main'
    );

    add_settings_field(
        'openai_tag_suggester_system_role',
        'System Role Prompt',
        'openai_tag_suggester_system_role_field',
        'openai_tag_suggester',
        'openai_tag_suggester_main'
    );

    add_settings_field(
        'openai_tag_suggester_user_role',
        'User Role Prompt',
        'openai_tag_suggester_user_role_field',
        'openai_tag_suggester',
        'openai_tag_suggester_main'
    );

    add_settings_field(
        'openai_tag_suggester_enabled_taxonomies',
        'Enabled Taxonomies',
        'openai_tag_suggester_enabled_taxonomies_field',
        'openai_tag_suggester',
        'openai_tag_suggester_taxonomies'
    );
}

// Add section description callback
function openai_tag_suggester_taxonomies_section_callback() {
    echo '<p>Select which taxonomies should be available for tag suggestions.</p>';
}

// Add taxonomy field renderer
function openai_tag_suggester_enabled_taxonomies_field() {
    $enabled_taxonomies = get_option('openai_tag_suggester_enabled_taxonomies', array('post_tag'));
    $available_taxonomies = openai_tag_suggester_get_available_taxonomies();
    
    echo '<div class="taxonomy-checkboxes">';
    foreach ($available_taxonomies as $tax_name => $tax_label) {
        $checked = in_array($tax_name, $enabled_taxonomies) ? 'checked' : '';
        echo '<label class="taxonomy-checkbox-label">';
        echo '<input type="checkbox" name="openai_tag_suggester_enabled_taxonomies[]" ';
        echo 'value="' . esc_attr($tax_name) . '" ' . $checked . '> ';
        echo esc_html($tax_label) . ' (' . esc_html($tax_name) . ')';
        echo '</label><br>';
    }
    echo '</div>';
    
    // Add debug output
    echo '<div class="taxonomy-debug">';
    echo '<pre>';
    echo 'Current enabled taxonomies: ' . print_r($enabled_taxonomies, true) . "\n";
    echo 'Available taxonomies: ' . print_r($available_taxonomies, true);
    echo '</pre>';
    echo '</div>';
}

function openai_tag_suggester_api_key_field() {
    $api_key = get_option('openai_tag_suggester_api_key');
    echo "<input type='text' size='50' name='openai_tag_suggester_api_key' value='" . esc_attr($api_key) . "' />";
}

function openai_tag_suggester_system_role_field() {
    $system_role = get_option('openai_tag_suggester_system_role');
    echo "<textarea name='openai_tag_suggester_system_role' rows='4' cols='50'>" . esc_textarea($system_role) . "</textarea>";
}

function openai_tag_suggester_user_role_field() {
    $user_role = get_option('openai_tag_suggester_user_role');
    echo "<textarea name='openai_tag_suggester_user_role' rows='4' cols='50'>" . esc_textarea($user_role) . "</textarea>";
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

function openai_tag_suggester_get_tags($content, $api_key, $taxonomy) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $cleaned_content = wp_strip_all_tags($content);
    $cleaned_content = substr($cleaned_content, 0, 2000);
    
    // Get custom prompts from settings
    $system_role = get_option('openai_tag_suggester_system_role');
    $user_role = get_option('openai_tag_suggester_user_role');
    
    // Modify prompt based on taxonomy
    $taxonomy_label = openai_tag_suggester_get_available_taxonomies()[$taxonomy];
    $user_role .= " Generate {$taxonomy_label}.";
    
    if ($taxonomy === 'hashtag') {
        $user_role .= " Include the # symbol with each suggestion.";
    }
    
    $data = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => array(
            array(
                'role' => 'system',
                'content' => $system_role
            ),
            array(
                'role' => 'user',
                'content' => $user_role . ": $cleaned_content"
            )
        ),
        'temperature' => 0.7,
        'max_tokens' => 150
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

// Add meta box to post editor screen
add_action('add_meta_boxes', 'openai_tag_suggester_add_meta_box');
function openai_tag_suggester_add_meta_box() {
    // Add our meta box AFTER the default tags box
    add_meta_box(
        'openai_tag_suggester_meta_box',
        'AI Tag Suggestions',
        'openai_tag_suggester_meta_box_callback',
        'post',
        'side',
        'low'  // Ensures it appears below the default tags box
    );
}

function openai_tag_suggester_meta_box_callback($post) {
    wp_nonce_field('openai_tag_suggester_nonce', 'openai_tag_suggester_nonce');
    
    echo '<div id="openai_tag_suggester_container" class="ai-tag-suggester">';
    
    // Instructions for users
    echo '<p class="howto">Generate AI-powered tag suggestions based on your content.</p>';
    
    // Taxonomy selector
    $enabled_taxonomies = get_option('openai_tag_suggester_enabled_taxonomies', array('post_tag'));
    echo '<div class="taxonomy-selector" style="margin-bottom: 10px;">';
    echo '<label for="openai_tag_suggester_taxonomy" class="howto">Select taxonomy:</label>';
    echo '<select id="openai_tag_suggester_taxonomy" name="openai_tag_suggester_taxonomy" class="widefat">';
    foreach (openai_tag_suggester_get_available_taxonomies() as $tax_name => $tax_label) {
        if (in_array($tax_name, $enabled_taxonomies)) {
            $selected = ($tax_name === 'post_tag') ? 'selected' : '';
            echo '<option value="' . esc_attr($tax_name) . '" ' . $selected . '>' . 
                 esc_html($tax_label) . '</option>';
        }
    }
    echo '</select>';
    echo '</div>';
    
    // Generate button
    echo '<button type="button" id="openai_tag_suggester_button" name="openai_tag_suggester_button" ';
    echo 'class="button button-primary" style="width: 100%; margin-bottom: 10px;">';
    echo 'Generate Tag Suggestions</button>';
    
    // Results area
    echo '<div id="openai_tag_suggester_results"></div>';
    
    echo '</div>';
}

// Add custom styling to separate the UIs
add_action('admin_head', function() {
    ?>
    <style>
        /* Style the AI Tag Suggester box */
        #openai_tag_suggester_meta_box {
            margin-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .ai-tag-suggester .tag-suggestion {
            margin: 5px 0;
            padding: 3px 0;
        }
        
        .ai-tag-suggester .tag-controls {
            margin: 10px 0;
            display: flex;
            gap: 5px;
            flex-wrap: wrap; /* Allow buttons to wrap on narrow screens */
        }
        
        .ai-tag-suggester .tag-controls .button {
            flex: 0 1 auto; /* Allow buttons to shrink but not grow */
        }
        
        .ai-tag-suggester .tag-controls .add-selected-tags {
            margin-left: auto; /* Push the Add Selected Tags button to the right */
        }
        
        .ai-tag-suggester .tag-list {
            max-height: 200px;
            overflow-y: auto;
            margin: 10px 0;
            padding: 5px;
            border: 1px solid #ddd;
            background: #fff;
        }
        
        .ai-tag-suggester .existing-tag {
            opacity: 0.7;
        }
        
        .ai-tag-suggester .existing-tag-label {
            color: #666;
            font-size: 0.9em;
            font-style: italic;
        }
        
        .ai-tag-suggester .notice {
            margin: 10px 0;
            padding: 5px 10px;
        }
        
        .ai-tag-suggester .notice-success {
            border-left: 4px solid #46b450;
        }
        
        .ai-tag-suggester .notice-error {
            border-left: 4px solid #dc3232;
        }
        
        .ai-tag-suggester .taxonomy-selector {
            margin-bottom: 15px;
        }
        
        .ai-tag-suggester .taxonomy-selector label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .ai-tag-suggester .taxonomy-selector select {
            width: 100%;
            max-width: 100%;
        }
        
        .ai-tag-suggester .add-selected-tags:last-child {
            width: 100%; /* Make the bottom button full width */
            margin-top: 10px;
        }
    </style>
    <?php
});

// Remove any old hooks
remove_action('post_tag_add_form_fields', 'openai_tag_suggester_inject_ui', 1);
remove_action('hashtag_add_form_fields', 'openai_tag_suggester_inject_ui', 1);

/**
 * Register the UI injection for each enabled taxonomy
 */
function openai_tag_suggester_register_taxonomy_hooks() {
    $enabled_taxonomies = get_option('openai_tag_suggester_enabled_taxonomies', array('post_tag'));
    
    error_log('=== Tag Suggester Hook Registration ===');
    error_log('Registering hooks for taxonomies: ' . print_r($enabled_taxonomies, true));
    
    foreach ($enabled_taxonomies as $taxonomy) {
        error_log('Adding hooks for taxonomy: ' . $taxonomy);
        
        // Add to the taxonomy meta box in post editor
        add_action('add_meta_boxes', function() use ($taxonomy) {
            add_meta_box(
                'openai_tag_suggester_' . $taxonomy,
                'Tag Suggestions for ' . ucfirst($taxonomy),
                'openai_tag_suggester_inject_ui',
                'post',
                'side',
                'high'
            );
        });
        
        // Add to the taxonomy page
        add_action($taxonomy . '_add_form_fields', 'openai_tag_suggester_inject_ui', 1);
        add_action($taxonomy . '_edit_form_fields', 'openai_tag_suggester_inject_ui', 1);
    }
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
    // Prevent any output before our JSON response
    if (ob_get_level()) ob_clean();
    
    // Verify nonce
    check_ajax_referer('openai_tag_suggester_nonce', 'nonce');

    // Debug logging (to file, not output)
    error_log('=== Tag Suggester AJAX Request ===');
    error_log('POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['taxonomy'])) {
        wp_send_json_error('No taxonomy specified in request');
        return;
    }

    $taxonomy = sanitize_text_field($_POST['taxonomy']);
    $post_id = intval($_POST['post_id']);
    
    error_log('Processing request for taxonomy: ' . $taxonomy);
    error_log('Post ID: ' . $post_id);
    
    // Validate taxonomy
    $enabled_taxonomies = get_option('openai_tag_suggester_enabled_taxonomies', array('post_tag'));
    if (!in_array($taxonomy, $enabled_taxonomies)) {
        wp_send_json_error('Invalid taxonomy: ' . $taxonomy);
        return;
    }

    // Get post content
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Invalid post ID');
        return;
    }

    // Get API key
    $api_key = get_option('openai_tag_suggester_api_key');
    if (!$api_key) {
        wp_send_json_error('API key not set');
        return;
    }

    try {
        // Make sure we don't have any output before sending JSON
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $tags = openai_tag_suggester_get_tags($post->post_content, $api_key, $taxonomy);
        if ($tags) {
            wp_send_json_success(array(
                'suggested' => $tags,
                'existing' => wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names'))
            ));
        } else {
            wp_send_json_error('No tag suggestions available');
        }
    } catch (Exception $e) {
        error_log('Tag generation error: ' . $e->getMessage());
        wp_send_json_error('Error generating tags: ' . $e->getMessage());
    }
    
    // Make sure we exit
    wp_die();
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
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    try {
        if (!check_ajax_referer('openai_tag_suggester_nonce', 'nonce', false)) {
            throw new Exception('Security check failed');
        }
        
        if (!isset($_POST['post_id']) || !isset($_POST['taxonomy']) || !isset($_POST['tags'])) {
            throw new Exception('Missing required data');
        }
        
        $post_id = intval($_POST['post_id']);
        $taxonomy = sanitize_text_field($_POST['taxonomy']);
        $new_tags = array_map('sanitize_text_field', $_POST['tags']);
        
        // Verify the taxonomy is registered
        if (!taxonomy_exists($taxonomy)) {
            throw new Exception('Invalid taxonomy');
        }
        
        // Get existing terms
        $existing_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names'));
        if (is_wp_error($existing_terms)) {
            throw new Exception($existing_terms->get_error_message());
        }
        
        // Merge existing and new tags
        $all_terms = array_unique(array_merge($existing_terms, $new_tags));
        
        // Set the terms
        $result = wp_set_object_terms($post_id, $all_terms, $taxonomy);
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'message' => 'Tags saved successfully',
                'tags' => $all_terms
            )
        ));
        
    } catch (Exception $e) {
        echo json_encode(array(
            'success' => false,
            'data' => $e->getMessage()
        ));
    }
    
    exit();
}

// Add this function to get available taxonomies
function openai_tag_suggester_get_available_taxonomies() {
    return array(
        'post_tag' => 'Tags',
        'hashtag' => 'Hashtags'
    );
}

// Add sanitization callback for taxonomies
function openai_tag_suggester_sanitize_taxonomies($taxonomies) {
    error_log('Sanitizing taxonomies: ' . print_r($taxonomies, true));
    
    if (!is_array($taxonomies)) {
        error_log('Taxonomies is not an array, defaulting to post_tag');
        return array('post_tag');
    }
    
    // Get available taxonomies
    $available_taxonomies = array_keys(openai_tag_suggester_get_available_taxonomies());
    error_log('Available taxonomies: ' . print_r($available_taxonomies, true));
    
    // Filter out any invalid taxonomies
    $taxonomies = array_filter($taxonomies, function($tax) use ($available_taxonomies) {
        return in_array($tax, $available_taxonomies);
    });
    
    error_log('Filtered taxonomies: ' . print_r($taxonomies, true));
    
    // Ensure we have at least post_tag
    if (empty($taxonomies)) {
        error_log('No valid taxonomies, defaulting to post_tag');
        return array('post_tag');
    }
    
    return array_values($taxonomies);
}

// Ensure default Tags meta box is present
add_action('init', function() {
    // Re-register the default post_tag taxonomy to ensure its meta box appears
    register_taxonomy('post_tag', 'post', array(
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'labels' => array(
            'name' => _x('Tags', 'taxonomy general name'),
            'singular_name' => _x('Tag', 'taxonomy singular name'),
            'menu_name' => __('Tags'),
        )
    ));
});

// Remove any code that might be interfering with the default Tags box
remove_action('add_meta_boxes', 'remove_default_tags_meta_box', 10);

// Register the hashtag taxonomy
add_action('init', function() {
    register_taxonomy('hashtag', 'post', array(
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'labels' => array(
            'name' => _x('Hashtags', 'taxonomy general name'),
            'singular_name' => _x('Hashtag', 'taxonomy singular name'),
            'menu_name' => __('Hashtags'),
            'add_new_item' => __('Add New Hashtag'),
            'new_item_name' => __('New Hashtag Name'),
        )
    ));
});

// Add meta boxes for both taxonomies
add_action('add_meta_boxes', 'openai_tag_suggester_add_meta_boxes');
function openai_tag_suggester_add_meta_boxes() {
    // Add AI Tag Suggestions box
    add_meta_box(
        'openai_tag_suggester_meta_box',
        'AI Tag Suggestions',
        'openai_tag_suggester_meta_box_callback',
        'post',
        'side',
        'low'
    );
}
?>