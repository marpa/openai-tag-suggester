<?php
/*
Plugin Name: OpenAI Tag Suggester
Description: Suggests tags for posts using the OpenAI API.
Version: 1.2.1
Author: Alex Chapin
GitHub Plugin URI: https://github.com/marpa/openai-tag-suggester.git
GitHub Branch: main
Update URI: https://github.com/marpa/openai-tag-suggester.git
Primary Branch: main
Release Asset: true
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
    
    // Model setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_model', array(
        'default' => 'gpt-3.5-turbo'
    ));
    
    // System Role setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_system_role', array(
        'default' => 'You are a university communications specialist. You are tasked with suggesting tags for faculty profiles that highlight their research interests. You must return ONLY a comma-separated list of tags.'
    ));
    
    // User Role setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_user_role', array(
        'default' => 'Please suggest tags for the following content. Return ONLY a comma-separated list of tags, with no additional text or explanation.'
    ));
    
    // Enabled taxonomies setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_enabled_taxonomies', array(
        'sanitize_callback' => 'openai_tag_suggester_sanitize_taxonomies',
        'default' => array('post_tag')
    ));
    
    // Taxonomy prompts setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_taxonomy_prompts', array(
        'sanitize_callback' => 'openai_tag_suggester_sanitize_taxonomy_prompts',
        'default' => array()
    ));
    
    // Content sources setting
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_content_sources', array(
        'sanitize_callback' => 'openai_tag_suggester_sanitize_content_sources',
        'default' => array('post_content')
    ));
    
    // Post types to scan for meta fields
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_post_types_to_scan', array(
        'sanitize_callback' => 'openai_tag_suggester_sanitize_post_types',
        'default' => array()
    ));
    
    // Debug mode for meta field detection
    register_setting('openai_tag_suggester_options', 'openai_tag_suggester_meta_debug_mode', array(
        'type' => 'boolean',
        'default' => false
    ));
    
    // Add settings sections
    add_settings_section(
        'openai_tag_suggester_api_section',
        'API Settings',
        'openai_tag_suggester_api_section_callback',
        'openai_tag_suggester'
    );
    
    add_settings_section(
        'openai_tag_suggester_taxonomies_section',
        'Taxonomy Settings',
        'openai_tag_suggester_taxonomies_section_callback',
        'openai_tag_suggester'
    );
    
    add_settings_section(
        'openai_tag_suggester_content_sources_section',
        'Content Sources',
        'openai_tag_suggester_content_sources_section_callback',
        'openai_tag_suggester'
    );
    
    add_settings_section(
        'openai_tag_suggester_meta_fields_section',
        'Meta Field Detection Settings',
        'openai_tag_suggester_meta_fields_section_callback',
        'openai_tag_suggester'
    );
    
    // Add settings fields
    add_settings_field(
        'openai_tag_suggester_api_key',
        'OpenAI API Key',
        'openai_tag_suggester_api_key_field',
        'openai_tag_suggester',
        'openai_tag_suggester_api_section'
    );
    
    add_settings_field(
        'openai_tag_suggester_model',
        'OpenAI Model',
        'openai_tag_suggester_model_field',
        'openai_tag_suggester',
        'openai_tag_suggester_api_section'
    );
    
    add_settings_field(
        'openai_tag_suggester_system_role',
        'System Role Prompt',
        'openai_tag_suggester_system_role_field',
        'openai_tag_suggester',
        'openai_tag_suggester_api_section'
    );
    
    add_settings_field(
        'openai_tag_suggester_user_role',
        'User Role Prompt',
        'openai_tag_suggester_user_role_field',
        'openai_tag_suggester',
        'openai_tag_suggester_api_section'
    );
    
    add_settings_field(
        'openai_tag_suggester_enabled_taxonomies',
        'Enabled Taxonomies',
        'openai_tag_suggester_enabled_taxonomies_field',
        'openai_tag_suggester',
        'openai_tag_suggester_taxonomies_section'
    );
    
    add_settings_field(
        'openai_tag_suggester_content_sources',
        'Content Sources',
        'openai_tag_suggester_content_sources_field',
        'openai_tag_suggester',
        'openai_tag_suggester_content_sources_section'
    );
    
    add_settings_field(
        'openai_tag_suggester_post_types_to_scan',
        'Post Types to Scan for Meta Fields',
        'openai_tag_suggester_post_types_field',
        'openai_tag_suggester',
        'openai_tag_suggester_meta_fields_section'
    );
}

// Add section description callback
function openai_tag_suggester_taxonomies_section_callback() {
    echo '<p>Select which taxonomies should be available for AI tag suggestions. The plugin will add a meta box for each enabled taxonomy.</p>';
}

// API section callback
function openai_tag_suggester_api_section_callback() {
    echo '<p>Configure your OpenAI API settings and prompts for tag generation.</p>';
    echo '<p>The System Role defines how the AI should behave, while the User Role provides specific instructions for tag generation.</p>';
}

// Add content sources section description callback
function openai_tag_suggester_content_sources_section_callback() {
    echo '<div class="content-sources-section-description">';
    echo '<p>Select which content sources should be used for generating tag suggestions. The plugin will use the selected sources to create a combined content input for the AI.</p>';
    echo '<p>You can select multiple sources to provide more context for tag generation. At least one source must be selected.</p>';
    echo '<p><strong>Tips:</strong></p>';
    echo '<ul style="list-style-type: disc; margin-left: 20px;">';
    echo '<li>Post Content is the main content of your post and is selected by default</li>';
    echo '<li>Meta fields can provide additional context for more accurate tag suggestions</li>';
    echo '<li>Selecting multiple sources may result in more comprehensive tag suggestions</li>';
    echo '<li>The plugin will automatically combine all selected sources before sending to the AI</li>';
    echo '</ul>';
    echo '</div>';
}

// Meta fields section callback
function openai_tag_suggester_meta_fields_section_callback() {
    echo '<div class="meta-fields-section-description">';
    echo '<p>Configure how the plugin detects and uses meta fields across your site.</p>';
    echo '<p>Limiting the post types to scan can improve performance on large sites with many custom fields.</p>';
    echo '<p><strong>Tips:</strong></p>';
    echo '<ul style="list-style-type: disc; margin-left: 20px;">';
    echo '<li>If no post types are selected, all public post types will be scanned</li>';
    echo '<li>The meta field cache is refreshed hourly to ensure new fields are detected</li>';
    echo '<li>Memory usage is optimized to prevent performance issues on large sites</li>';
    echo '</ul>';
    echo '</div>';
    
    // Add debug mode toggle
    $debug_mode = get_option('openai_tag_suggester_meta_debug_mode', false);
    echo '<div class="debug-mode-toggle" style="margin-top: 15px; margin-bottom: 15px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
    echo '<label for="openai_tag_suggester_meta_debug_mode">';
    echo '<input type="checkbox" id="openai_tag_suggester_meta_debug_mode" name="openai_tag_suggester_meta_debug_mode" value="1" ' . checked(1, $debug_mode, false) . '> ';
    echo 'Enable Debug Mode for Meta Field Detection';
    echo '</label>';
    echo '<p class="description" style="margin-top: 5px;">When enabled, detailed debug information will be shown about the meta field detection process.</p>';
    echo '</div>';
    
    // Direct database check for meta fields
    global $wpdb;
    $meta_count = $wpdb->get_var("SELECT COUNT(DISTINCT meta_key) FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE '\_%'");
    $sample_meta_keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key NOT LIKE '\_%' LIMIT 10");
    
    // Also check for internal meta keys (starting with underscore)
    $internal_meta_count = $wpdb->get_var("SELECT COUNT(DISTINCT meta_key) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_%'");
    $sample_internal_meta_keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_%' LIMIT 10");
    
    // Get all meta keys for a specific post type
    $faculty_meta_keys = array();
    $faculty_posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'faculty_success' LIMIT 1");
    if (!empty($faculty_posts)) {
        $faculty_post_id = $faculty_posts[0];
        $faculty_meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d",
            $faculty_post_id
        ));
    }
    
    echo '<div class="direct-db-check" style="margin-top: 15px; padding: 10px; background-color: #f0f7f4; border: 1px solid #005035; border-radius: 4px;">';
    echo '<h4 style="margin-top: 0; color: #005035;">Database Check</h4>';
    echo '<p>Direct database query found <strong>' . intval($meta_count) . '</strong> distinct meta keys in your database.</p>';
    
    if (!empty($sample_meta_keys)) {
        echo '<p>Sample meta keys found in database:</p>';
        echo '<ul style="margin-left: 20px; list-style-type: disc;">';
        foreach ($sample_meta_keys as $key) {
            echo '<li>' . esc_html($key) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No meta keys found in your database. This could indicate that your site doesn\'t use custom fields or there\'s an issue with the database.</p>';
    }
    
    echo '</div>';
    
    // Show internal meta keys
    if (!empty($sample_internal_meta_keys)) {
        echo '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
        echo '<p>Found <strong>' . intval($internal_meta_count) . '</strong> internal meta keys (starting with underscore):</p>';
        echo '<ul style="margin-left: 20px; list-style-type: disc;">';
        foreach ($sample_internal_meta_keys as $key) {
            echo '<li>' . esc_html($key) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    // Show meta keys for faculty_success post type
    if (!empty($faculty_meta_keys)) {
        echo '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
        echo '<p>Meta keys for faculty_success post (ID: ' . $faculty_post_id . '):</p>';
        echo '<ul style="margin-left: 20px; list-style-type: disc;">';
        foreach ($faculty_meta_keys as $key) {
            echo '<li>' . esc_html($key) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    echo '</div>';
    
    // Add buttons to manually clear the meta fields cache and perform a scan
    echo '<div class="clear-cache-container" style="margin-top: 15px;">';
    echo '<button type="button" id="scan-meta-fields" class="button button-primary">Scan Meta Fields Now</button> ';
    echo '<button type="button" id="clear-meta-fields-cache" class="button">Clear Meta Fields Cache</button>';
    echo '<span id="cache-cleared-message" style="display: none; margin-left: 10px; color: green;">Cache cleared successfully!</span>';
    echo '<span id="scan-complete-message" style="display: none; margin-left: 10px; color: green;">Scan completed successfully!</span>';
    echo '</div>';
    
    // Display current meta fields if they exist
    $meta_fields = get_transient('openai_tag_suggester_meta_fields_cache');
    if (is_array($meta_fields) && !empty($meta_fields)) {
        echo '<div class="current-meta-fields" style="margin-top: 15px;">';
        echo '<h4>Currently Detected Meta Fields (' . count($meta_fields) . ')</h4>';
        echo '<div class="meta-fields-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 10px; background-color: #f9f9f9;">';
        
        foreach ($meta_fields as $meta_field) {
            echo '<div class="meta-field-item">' . esc_html($meta_field) . '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="no-meta-fields" style="margin-top: 15px; font-style: italic;">';
        echo 'No meta fields have been detected yet. Click "Scan Meta Fields Now" to perform a scan.';
        echo '</div>';
    }
    
    // Display debug information if debug mode is enabled
    if ($debug_mode) {
        // Get debug log
        $debug_log = get_option('openai_tag_suggester_meta_debug_log', array());
        
        echo '<div class="meta-fields-debug" style="margin-top: 20px; border: 1px solid #ddd; padding: 15px; background-color: #f0f0f0; border-radius: 4px;">';
        echo '<h4 style="margin-top: 0; color: #333;">Debug Information</h4>';
        
        if (!empty($debug_log)) {
            echo '<div class="debug-log" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background-color: #fff; font-family: monospace; font-size: 12px;">';
            foreach ($debug_log as $log_entry) {
                echo '<div class="debug-log-entry">' . esc_html($log_entry) . '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>No debug information available. Run a scan to generate debug information.</p>';
        }
        
        echo '</div>';
    }
    
    // Add JavaScript for the buttons
    echo '<script>
        jQuery(document).ready(function($) {
            $("#clear-meta-fields-cache").on("click", function() {
                $(this).prop("disabled", true).text("Clearing...");
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "openai_tag_suggester_clear_cache",
                        nonce: "' . wp_create_nonce('openai_tag_suggester_clear_cache') . '"
                    },
                    success: function(response) {
                        $("#clear-meta-fields-cache").prop("disabled", false).text("Clear Meta Fields Cache");
                        if (response.success) {
                            $("#cache-cleared-message").fadeIn().delay(3000).fadeOut();
                            // Reload the page to show updated meta fields list
                            location.reload();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Clear cache error:", status, error);
                        alert("Error clearing cache: " + error);
                        $("#clear-meta-fields-cache").prop("disabled", false).text("Clear Meta Fields Cache");
                    }
                });
            });
            
            $("#scan-meta-fields").on("click", function() {
                console.log("Scan button clicked");
                $(this).prop("disabled", true).text("Scanning...");
                
                // Add a status message
                if ($("#scan-status").length === 0) {
                    $("<div id=\'scan-status\' style=\'margin-top: 10px; padding: 5px; background-color: #f0f0f0; border: 1px solid #ddd;\'>Starting scan...</div>").insertAfter(this);
                } else {
                    $("#scan-status").text("Starting scan...").show();
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "openai_tag_suggester_scan_meta_fields",
                        nonce: "' . wp_create_nonce('openai_tag_suggester_scan_meta_fields') . '"
                    },
                    success: function(response) {
                        console.log("Scan response:", response);
                        $("#scan-meta-fields").prop("disabled", false).text("Scan Meta Fields Now");
                        $("#scan-status").text("Scan completed. Found " + (response.data && response.data.count ? response.data.count : 0) + " meta fields.").show();
                        
                        if (response.success) {
                            $("#scan-complete-message").fadeIn().delay(3000).fadeOut();
                            // Reload the page to show updated meta fields list
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            $("#scan-status").text("Scan failed: " + (response.data || "Unknown error")).show();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Scan error:", xhr.responseText, status, error);
                        $("#scan-meta-fields").prop("disabled", false).text("Scan Meta Fields Now");
                        $("#scan-status").text("Error during scan: " + error + ". Check browser console for details.").show();
                        
                        // Try to parse the response for more details
                        try {
                            var responseJson = JSON.parse(xhr.responseText);
                            console.log("Parsed error response:", responseJson);
                        } catch(e) {
                            console.log("Raw error response:", xhr.responseText);
                        }
                    }
                });
            });
            
        });
    </script>';
}

// Post types field renderer
function openai_tag_suggester_post_types_field() {
    $selected_post_types = get_option('openai_tag_suggester_post_types_to_scan', array());
    $post_types = get_post_types(array('public' => true), 'objects');
    
    echo '<div class="post-types-container">';
    echo '<p class="description">Select which post types should be scanned for meta fields. If none are selected, all public post types will be scanned.</p>';
    
    if (!empty($post_types)) {
        echo '<div class="post-types-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 10px;">';
        
        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $selected_post_types) ? 'checked' : '';
            
            echo '<div class="post-type-item">';
            echo '<label>';
            echo '<input type="checkbox" name="openai_tag_suggester_post_types_to_scan[]" value="' . esc_attr($post_type->name) . '" ' . $checked . '>';
            echo ' ' . esc_html($post_type->label) . ' (' . esc_html($post_type->name) . ')';
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add select all/none buttons for post types
        echo '<div class="post-type-controls" style="margin-top: 5px;">';
        echo '<button type="button" class="button select-all-post-types">Select All</button> ';
        echo '<button type="button" class="button select-none-post-types">Select None</button>';
        echo '</div>';
        
        // Add JavaScript for select all/none functionality
        echo '<script>
            jQuery(document).ready(function($) {
                $(".select-all-post-types").on("click", function(e) {
                    e.preventDefault();
                    $(".post-types-list input[type=checkbox]").prop("checked", true);
                });
                
                $(".select-none-post-types").on("click", function(e) {
                    e.preventDefault();
                    $(".post-types-list input[type=checkbox]").prop("checked", false);
                });
            });
        </script>';
    } else {
        echo '<p><em>No public post types found.</em></p>';
    }
    
    echo '</div>';
}

// Function to scan and detect all active meta fields across the site
function openai_tag_suggester_get_meta_fields() {
    // Initialize debug log
    $debug_log = array();
    $debug_mode = get_option('openai_tag_suggester_meta_debug_mode', false);
    
    $debug_log[] = date('[Y-m-d H:i:s]') . ' Starting meta field scan...';
    
    // Check if we have cached meta fields and they're not expired
    $cached_meta_fields = get_transient('openai_tag_suggester_meta_fields_cache');
    if (false !== $cached_meta_fields && !isset($_POST['force_scan'])) {
        $debug_log[] = 'Using cached meta fields (' . count($cached_meta_fields) . ' fields)';
        
        if ($debug_mode) {
            update_option('openai_tag_suggester_meta_debug_log', $debug_log);
        }
        
        return $cached_meta_fields;
    }
    
    // Get post types to scan from settings, default to all public post types if not set
    $post_types_to_scan = get_option('openai_tag_suggester_post_types_to_scan', array());
    if (empty($post_types_to_scan)) {
        $all_post_types = get_post_types(array('public' => true), 'names');
        $post_types_to_scan = array_keys($all_post_types);
        $debug_log[] = 'No post types selected, scanning all public post types: ' . implode(', ', $post_types_to_scan);
    } else {
        $debug_log[] = 'Scanning selected post types: ' . implode(', ', $post_types_to_scan);
    }
    
    // Initialize meta keys array
    $meta_keys = array();
    
    // Memory usage tracking
    $initial_memory = memory_get_usage();
    $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit')) * 0.8; // Use 80% of available memory as limit
    $debug_log[] = 'Memory limit: ' . size_format($memory_limit) . ', Initial memory usage: ' . size_format($initial_memory);
    
    // Method 1: Get registered meta keys using WordPress API
    $registered_meta = get_registered_meta_keys('post');
    if (!empty($registered_meta)) {
        $debug_log[] = 'Found ' . count($registered_meta) . ' registered meta keys via get_registered_meta_keys()';
        foreach ($registered_meta as $meta_key => $meta_data) {
            // Skip internal meta keys
            if (substr($meta_key, 0, 1) === '_') {
                continue;
            }
            
            // Add to our list if not already there
            if (!in_array($meta_key, $meta_keys)) {
                $meta_keys[] = $meta_key;
                $debug_log[] = 'Added registered meta key: ' . $meta_key;
            }
        }
    } else {
        $debug_log[] = 'No registered meta keys found via get_registered_meta_keys()';
    }
    
    // Method 2: Direct database query to find meta keys
    global $wpdb;
    $query = "
        SELECT DISTINCT meta_key 
        FROM {$wpdb->postmeta} 
        WHERE meta_key NOT LIKE '\_%' 
        ORDER BY meta_key
    ";
    
    $db_meta_keys = $wpdb->get_col($query);
    if (!empty($db_meta_keys)) {
        $debug_log[] = 'Found ' . count($db_meta_keys) . ' meta keys via direct database query';
        foreach ($db_meta_keys as $meta_key) {
            if (!in_array($meta_key, $meta_keys)) {
                $meta_keys[] = $meta_key;
                if ($debug_mode) {
                    $debug_log[] = 'Added meta key from database: ' . $meta_key;
                }
            }
        }
    } else {
        $debug_log[] = 'No meta keys found via direct database query';
    }
    
    // Method 3: Check for Advanced Custom Fields if the plugin is active
    if (function_exists('acf_get_field_groups')) {
        $debug_log[] = 'ACF is active, checking for ACF fields';
        $field_groups = acf_get_field_groups();
        $debug_log[] = 'Found ' . count($field_groups) . ' ACF field groups';
        
        foreach ($field_groups as $field_group) {
            $fields = acf_get_fields($field_group);
            
            if (!empty($fields)) {
                $debug_log[] = 'Found ' . count($fields) . ' fields in group: ' . $field_group['title'];
                foreach ($fields as $field) {
                    if (!in_array($field['name'], $meta_keys)) {
                        $meta_keys[] = $field['name'];
                        $debug_log[] = 'Added ACF field: ' . $field['name'] . ' (' . $field['label'] . ')';
                    }
                }
            }
        }
    } else {
        $debug_log[] = 'ACF is not active';
    }
    
    // Method 4: For each post type, get a sample of posts and their meta keys
    foreach ($post_types_to_scan as $post_type) {
        // Check memory usage and break if we're approaching the limit
        if (memory_get_usage() > $memory_limit) {
            $debug_log[] = 'Memory limit approaching, stopping meta field scan';
            break;
        }
        
        $debug_log[] = "Scanning post type: $post_type";
        
        // Get a larger sample of posts for this type
        $posts = get_posts(array(
            'post_type' => $post_type,
            'posts_per_page' => 10, // Increased from 5 to 10
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        if (!empty($posts)) {
            $debug_log[] = "Found " . count($posts) . " posts of type $post_type";
            foreach ($posts as $post) {
                // Check memory usage again
                if (memory_get_usage() > $memory_limit) {
                    $debug_log[] = 'Memory limit approaching, breaking scan loop';
                    break 2; // Break out of both loops
                }
                
                // Get meta keys for this post
                $post_meta_keys = get_post_custom_keys($post->ID);
                
                // Check if get_post_custom_keys returned null or false
                if ($post_meta_keys === null || $post_meta_keys === false) {
                    $debug_log[] = "get_post_custom_keys returned " . ($post_meta_keys === null ? "null" : "false") . " for post ID {$post->ID}";
                    
                    // Try alternative method to get meta keys
                    $debug_log[] = "Trying alternative method to get meta keys for post ID {$post->ID}";
                    global $wpdb;
                    $post_meta_keys = $wpdb->get_col($wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d",
                        $post->ID
                    ));
                }
                
                if (!empty($post_meta_keys)) {
                    $debug_log[] = "Found " . count($post_meta_keys) . " meta keys for post ID {$post->ID}";
                    
                    // For debugging, show all meta keys found for this post
                    if ($debug_mode) {
                        $debug_log[] = "All meta keys for post {$post->ID}: " . implode(", ", $post_meta_keys);
                    }
                    
                    foreach ($post_meta_keys as $meta_key) {
                        // Skip internal meta keys
                        if (substr($meta_key, 0, 1) === '_') {
                            if ($debug_mode) {
                                $debug_log[] = "Skipping internal meta key: " . $meta_key;
                            }
                            continue;
                        }
                        
                        // Add to our list if not already there
                        if (!in_array($meta_key, $meta_keys)) {
                            $meta_keys[] = $meta_key;
                            $debug_log[] = "Added meta key from post {$post->ID}: " . $meta_key;
                        } else {
                            if ($debug_mode) {
                                $debug_log[] = "Meta key already in list: " . $meta_key;
                            }
                        }
                    }
                } else {
                    $debug_log[] = "No meta keys found for post ID {$post->ID}";
                }
            }
        } else {
            $debug_log[] = "No posts found for post type $post_type";
        }
    }
    
    // Method 5: Check for common meta keys used by popular plugins
    $common_meta_keys = array(
        'subtitle', 'excerpt', 'secondary_title', 'seo_title', 'seo_description',
        'featured_image_alt', 'featured_image_caption', 'author_bio', 'page_template',
        'custom_css', 'custom_js', 'video_url', 'audio_url', 'gallery_images',
        'related_posts', 'post_views', 'post_likes', 'post_shares', 'post_rating',
        'event_date', 'event_location', 'event_organizer', 'product_price', 'product_sku',
        'product_stock', 'download_count', 'attachment_url', 'attachment_description'
    );
    
    $debug_log[] = "Checking for " . count($common_meta_keys) . " common meta keys";
    $found_common_keys = 0;
    
    foreach ($common_meta_keys as $meta_key) {
        if (!in_array($meta_key, $meta_keys)) {
            // Check if this meta key exists in the database
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
                $meta_key
            ));
            
            if ($exists) {
                $meta_keys[] = $meta_key;
                $found_common_keys++;
                $debug_log[] = "Added common meta key: " . $meta_key;
            }
        }
    }
    
    $debug_log[] = "Found $found_common_keys common meta keys";
    
    // Method 6: Manually add faculty_success meta fields
    if (in_array('faculty_success', $post_types_to_scan)) {
        $debug_log[] = "Manually checking for faculty_success meta fields";
        
        // Get all meta keys for faculty_success posts directly from the database
        // Include underscore-prefixed fields specifically for faculty_success
        $faculty_meta_keys = $wpdb->get_col("
            SELECT DISTINCT pm.meta_key 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'faculty_success'
        ");
        
        if (!empty($faculty_meta_keys)) {
            $debug_log[] = "Found " . count($faculty_meta_keys) . " faculty_success meta keys in database";
            
            foreach ($faculty_meta_keys as $meta_key) {
                // For faculty meta fields, include those with underscores but exclude WordPress internal ones
                $is_wp_internal = in_array($meta_key, array('_edit_lock', '_edit_last', '_wp_page_template', '_wp_trash_meta_status', '_wp_trash_meta_time'));
                
                if (strpos($meta_key, '_faculty_') === 0 || strpos($meta_key, '_search_') === 0) {
                    // Remove the leading underscore for display and usage
                    $display_key = substr($meta_key, 1);
                    
                    if (!in_array($display_key, $meta_keys) && !$is_wp_internal) {
                        $meta_keys[] = $display_key;
                        $debug_log[] = "Added faculty_success meta key (without underscore): " . $display_key . " (original: " . $meta_key . ")";
                    }
                } elseif (!in_array($meta_key, $meta_keys) && substr($meta_key, 0, 1) !== '_') {
                    // For non-underscore keys, add them normally
                    $meta_keys[] = $meta_key;
                    $debug_log[] = "Added faculty_success meta key: " . $meta_key;
                }
            }
        } else {
            $debug_log[] = "No faculty_success meta keys found in database";
        }
        
        // Hardcoded common faculty_success meta fields
        $faculty_common_fields = array(
            'faculty_name', 'faculty_title', 'faculty_department', 'faculty_email',
            'faculty_phone', 'faculty_office', 'faculty_bio', 'faculty_research',
            'faculty_education', 'faculty_publications', 'faculty_awards',
            'faculty_courses', 'faculty_expertise', 'faculty_website'
        );
        
        $debug_log[] = "Checking for " . count($faculty_common_fields) . " common faculty meta fields";
        $found_faculty_fields = 0;
        
        foreach ($faculty_common_fields as $meta_key) {
            if (!in_array($meta_key, $meta_keys)) {
                // Check if this meta key exists in the database
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
                    $meta_key
                ));
                
                if ($exists) {
                    $meta_keys[] = $meta_key;
                    $found_faculty_fields++;
                    $debug_log[] = "Added faculty meta key: " . $meta_key;
                }
            }
        }
        
        $debug_log[] = "Found $found_faculty_fields common faculty meta fields";
    }
    
    // Sort meta keys alphabetically
    sort($meta_keys);
    
    $debug_log[] = 'Final meta keys count: ' . count($meta_keys);
    
    // Cache the results for 1 hour (3600 seconds)
    set_transient('openai_tag_suggester_meta_fields_cache', $meta_keys, 3600);
    
    // Log memory usage for debugging
    $memory_used = memory_get_usage() - $initial_memory;
    $debug_log[] = sprintf('Meta field scan used %s of memory, found %d meta fields', 
        size_format($memory_used), count($meta_keys));
    
    // Save debug log if debug mode is enabled
    if ($debug_mode) {
        update_option('openai_tag_suggester_meta_debug_log', $debug_log);
    }
    
    return $meta_keys;
}

// Function to clear the meta fields cache
function openai_tag_suggester_clear_meta_fields_cache() {
    delete_transient('openai_tag_suggester_meta_fields_cache');
}

// Add content sources field renderer
function openai_tag_suggester_content_sources_field() {
    $content_sources = get_option('openai_tag_suggester_content_sources', array('post_content'));
    
    echo '<div class="content-sources-container">';
    
    // Post Content checkbox (always available)
    echo '<div class="content-source-item">';
    echo '<label>';
    echo '<input type="checkbox" name="openai_tag_suggester_content_sources[]" value="post_content" ' . 
         (in_array('post_content', $content_sources) ? 'checked' : '') . '>';
    echo ' <strong>Post Content</strong> (main post content)';
    echo '</label>';
    echo '</div>';
    
    // Add general select all/none buttons
    echo '<div class="meta-field-controls general-controls">';
    echo '<button type="button" class="button select-all-meta-fields">Select All Meta Fields</button> ';
    echo '<button type="button" class="button select-none-meta-fields">Select None</button>';
    echo '</div>';
    
    // Get all meta keys using our optimized function
    $meta_fields = get_transient('openai_tag_suggester_meta_fields_cache');
    
    // If cache is empty, run the scan
    if (empty($meta_fields)) {
        $meta_fields = openai_tag_suggester_get_meta_fields();
    }
    
    // Group meta fields by post type
    $grouped_meta_fields = array(
        'faculty_success' => array(),
        'post' => array(),
        'page' => array(),
        'other' => array()
    );
    
    // Get meta fields for each post type directly from the database
    global $wpdb;
    $post_types = get_post_types(array('public' => true), 'names');
    
    foreach ($post_types as $post_type) {
        // Skip attachment post type
        if ($post_type === 'attachment') {
            continue;
        }
        
        // Get meta keys for this post type
        $post_type_meta_keys = $wpdb->get_col("
            SELECT DISTINCT pm.meta_key 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = '{$post_type}'
            AND pm.meta_key NOT LIKE '\_%'
        ");
        
        // Add to grouped meta fields
        if (!isset($grouped_meta_fields[$post_type])) {
            $grouped_meta_fields[$post_type] = array();
        }
        
        foreach ($post_type_meta_keys as $meta_key) {
            if (!in_array($meta_key, $grouped_meta_fields[$post_type])) {
                $grouped_meta_fields[$post_type][] = $meta_key;
            }
        }
    }
    
    // Add any remaining meta fields to 'other'
    foreach ($meta_fields as $meta_key) {
        $found = false;
        foreach ($grouped_meta_fields as $post_type => $post_type_meta_keys) {
            if (in_array($meta_key, $post_type_meta_keys)) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $grouped_meta_fields['other'][] = $meta_key;
        }
    }
    
    // Display meta fields grouped by post type
    foreach ($grouped_meta_fields as $post_type => $post_type_meta_keys) {
        if (empty($post_type_meta_keys)) {
            continue;
        }
        
        // Get post type label
        $post_type_obj = get_post_type_object($post_type);
        $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : ucfirst($post_type);
        
        echo '<h4>' . esc_html($post_type_label) . ' Meta Fields</h4>';
        echo '<div class="meta-fields-container post-type-' . esc_attr($post_type) . '">';
        
        // Sort meta keys alphabetically
        sort($post_type_meta_keys);
        
        foreach ($post_type_meta_keys as $meta_key) {
            $field_id = 'meta_' . sanitize_key($meta_key);
            
            echo '<div class="content-source-item">';
            echo '<label>';
            echo '<input type="checkbox" name="openai_tag_suggester_content_sources[]" value="' . esc_attr($meta_key) . '" ' . 
                 (in_array($meta_key, $content_sources) ? 'checked' : '') . '>';
            echo ' ' . esc_html($meta_key);
            echo '</label>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add select all/none buttons for this post type's meta fields
        echo '<div class="meta-field-controls">';
        echo '<button type="button" class="button select-all-' . esc_attr($post_type) . '-meta-fields">Select All ' . esc_html($post_type_label) . ' Fields</button> ';
        echo '<button type="button" class="button select-none-' . esc_attr($post_type) . '-meta-fields">Select None</button>';
        echo '</div>';
    }
    
    // Add JavaScript for select all/none functionality
    echo '<script>
        jQuery(document).ready(function($) {
            // General select all/none buttons
            $(".select-all-meta-fields").on("click", function(e) {
                e.preventDefault();
                $(".meta-fields-container input[type=checkbox]").prop("checked", true);
            });
            
            $(".select-none-meta-fields").on("click", function(e) {
                e.preventDefault();
                $(".meta-fields-container input[type=checkbox]").prop("checked", false);
            });
            
            // Post type specific select all/none buttons
            ';
    
    foreach ($grouped_meta_fields as $post_type => $post_type_meta_keys) {
        if (empty($post_type_meta_keys)) {
            continue;
        }
        
        echo '
            $(".select-all-' . esc_attr($post_type) . '-meta-fields").on("click", function(e) {
                e.preventDefault();
                $(".post-type-' . esc_attr($post_type) . ' input[type=checkbox]").prop("checked", true);
            });
            
            $(".select-none-' . esc_attr($post_type) . '-meta-fields").on("click", function(e) {
                e.preventDefault();
                $(".post-type-' . esc_attr($post_type) . ' input[type=checkbox]").prop("checked", false);
            });
        ';
    }
    
    echo '
        });
    </script>';
    
    echo '</div>';
}

// Add taxonomy field renderer
function openai_tag_suggester_enabled_taxonomies_field() {
    $enabled_taxonomies = get_option('openai_tag_suggester_enabled_taxonomies', array('post_tag'));
    $available_taxonomies = openai_tag_suggester_get_available_taxonomies();
    $taxonomy_prompts = get_option('openai_tag_suggester_taxonomy_prompts', array());
    
    // Get taxonomy objects to access more information
    $taxonomy_objects = get_taxonomies(array('show_ui' => true), 'objects');
    
    // Group taxonomies by object type (post types they're associated with)
    $grouped_taxonomies = array();
    $core_taxonomies = array();
    
    foreach ($taxonomy_objects as $tax) {
        if (in_array($tax->name, array('link_category'))) {
            continue; // Skip internal taxonomies
        }
        
        // Check if it's a core taxonomy
        if (in_array($tax->name, array('category', 'post_tag', 'post_format'))) {
            $core_taxonomies[$tax->name] = $tax;
            continue;
        }
        
        // Group by object type
        if (!empty($tax->object_type)) {
            foreach ($tax->object_type as $object_type) {
                if (!isset($grouped_taxonomies[$object_type])) {
                    $grouped_taxonomies[$object_type] = array();
                }
                $grouped_taxonomies[$object_type][$tax->name] = $tax;
            }
        } else {
            // Fallback for taxonomies without object types
            if (!isset($grouped_taxonomies['other'])) {
                $grouped_taxonomies['other'] = array();
            }
            $grouped_taxonomies['other'][$tax->name] = $tax;
        }
    }
    
    // Add search filter
    echo '<div class="taxonomy-filter">';
    echo '<input type="text" id="taxonomy-search" class="widefat" placeholder="Search taxonomies..." style="margin-bottom: 15px;">';
    echo '</div>';
    
    echo '<div class="taxonomy-checkboxes">';
    
    // First show core WordPress taxonomies
    if (!empty($core_taxonomies)) {
        echo '<div class="taxonomy-group">';
        echo '<h3>WordPress Core Taxonomies</h3>';
        
        foreach ($core_taxonomies as $tax) {
            output_taxonomy_item($tax, $enabled_taxonomies, $taxonomy_prompts);
        }
        
        echo '</div>';
    }
    
    // Then show other taxonomies grouped by post type
    foreach ($grouped_taxonomies as $object_type => $taxonomies) {
        if (empty($taxonomies)) continue;
        
        // Get post type label if available
        $post_type_obj = get_post_type_object($object_type);
        $group_label = $post_type_obj ? $post_type_obj->labels->name : ucfirst($object_type);
        
        echo '<div class="taxonomy-group">';
        echo '<h3>' . esc_html($group_label) . ' Taxonomies</h3>';
        
        foreach ($taxonomies as $tax) {
            output_taxonomy_item($tax, $enabled_taxonomies, $taxonomy_prompts);
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    
    // Add JavaScript to show/hide prompt fields based on checkbox state and filter taxonomies
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Show/hide prompt fields
        $('.taxonomy-checkbox').on('change', function() {
            var taxonomy = $(this).data('taxonomy');
            if ($(this).is(':checked')) {
                $('#prompt-' + taxonomy).slideDown();
            } else {
                $('#prompt-' + taxonomy).slideUp();
            }
        });
        
        // Filter taxonomies
        $('#taxonomy-search').on('keyup', function() {
            var searchText = $(this).val().toLowerCase();
            
            $('.taxonomy-item').each(function() {
                var taxonomyName = $(this).find('.taxonomy-checkbox-label').text().toLowerCase();
                if (taxonomyName.indexOf(searchText) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            // Show/hide group headers based on visible items
            $('.taxonomy-group').each(function() {
                var visibleItems = $(this).find('.taxonomy-item:visible').length;
                if (visibleItems > 0) {
                    $(this).show();
                    $(this).find('h3').show();
                } else {
                    $(this).hide();
                }
            });
        });
    });
    </script>
    <?php
    
    // Add debug output
    echo '<div class="taxonomy-debug">';
    echo '<pre>';
    echo 'Current enabled taxonomies: ' . print_r($enabled_taxonomies, true) . "\n";
    echo 'Available taxonomies: ' . print_r($available_taxonomies, true) . "\n";
    echo 'Taxonomy prompts: ' . print_r($taxonomy_prompts, true);
    echo '</pre>';
    echo '</div>';
}

// Helper function to output a taxonomy item
function output_taxonomy_item($tax, $enabled_taxonomies, $taxonomy_prompts) {
    $tax_name = $tax->name;
    $tax_label = $tax->label;
    
    $checked = in_array($tax_name, $enabled_taxonomies) ? 'checked' : '';
    $display_style = in_array($tax_name, $enabled_taxonomies) ? 'block' : 'none';
    $prompt = isset($taxonomy_prompts[$tax_name]) ? $taxonomy_prompts[$tax_name] : '';
    
    echo '<div class="taxonomy-item">';
    echo '<label class="taxonomy-checkbox-label">';
    echo '<input type="checkbox" name="openai_tag_suggester_enabled_taxonomies[]" ';
    echo 'class="taxonomy-checkbox" data-taxonomy="' . esc_attr($tax_name) . '" ';
    echo 'value="' . esc_attr($tax_name) . '" ' . $checked . '> ';
    echo esc_html($tax_label) . ' <span class="taxonomy-name">(' . esc_html($tax_name) . ')</span>';
    echo '</label>';
    
    echo '<div class="taxonomy-prompt-container" id="prompt-' . esc_attr($tax_name) . '" style="display: ' . $display_style . ';">';
    echo '<p><label for="taxonomy-prompt-' . esc_attr($tax_name) . '">Custom prompt for ' . esc_html($tax_label) . ':</label></p>';
    echo '<textarea name="openai_tag_suggester_taxonomy_prompts[' . esc_attr($tax_name) . ']" ';
    echo 'id="taxonomy-prompt-' . esc_attr($tax_name) . '" rows="4" cols="50" class="widefat">';
    echo esc_textarea($prompt);
    echo '</textarea>';
    echo '<p class="description">Leave blank to use the default prompt. This prompt will be used specifically for ' . esc_html($tax_label) . '.</p>';
    echo '</div>';
    echo '</div>';
}

function openai_tag_suggester_api_key_field() {
    $api_key = get_option('openai_tag_suggester_api_key');
    echo "<input type='text' size='50' name='openai_tag_suggester_api_key' value='" . esc_attr($api_key) . "' />";
}

function openai_tag_suggester_model_field() {
    $model = get_option('openai_tag_suggester_model', 'gpt-3.5-turbo');
    $models = array(
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Faster, less accurate)',
        'gpt-4' => 'GPT-4 (Slower, more accurate)',
        'gpt-4-turbo' => 'GPT-4 Turbo (Balanced)'
    );
    
    echo '<select name="openai_tag_suggester_model">';
    foreach ($models as $model_id => $model_name) {
        $selected = ($model === $model_id) ? 'selected' : '';
        echo '<option value="' . esc_attr($model_id) . '" ' . $selected . '>' . esc_html($model_name) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">GPT-4 models follow instructions better but cost more and are slower.</p>';
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
    // Get API key
    $api_key = get_option('openai_tag_suggester_api_key');
    if (!$api_key) {
        return;
    }
    
    // Get content sources from settings
    $content_sources = get_option('openai_tag_suggester_content_sources', array('post_content'));
    
    // Initialize combined content
    $combined_content = '';
    
    // Process each content source
    foreach ($content_sources as $source) {
        if ($source === 'post_content') {
            // Get post content
            $post_content = $post->post_content;
            
            // Add to combined content with a heading
            $combined_content .= "POST CONTENT:\n" . $post_content . "\n\n";
        } else {
            // This is a meta field
            $meta_value = get_post_meta($post_id, $source, true);
            
            if (!empty($meta_value)) {
                // If it's an array or object, convert to string
                if (is_array($meta_value) || is_object($meta_value)) {
                    $meta_value = print_r($meta_value, true);
                }
                
                // Add to combined content with a heading
                $combined_content .= "META FIELD (" . $source . "):\n" . $meta_value . "\n\n";
            }
        }
    }
    
    // If no content was found, return
    if (empty($combined_content)) {
        return;
    }
    
    // Get tags from OpenAI
    $tags = openai_tag_suggester_get_tags($combined_content, $api_key);
    
    // Process and save tags
    if ($tags) {
        wp_set_post_tags($post_id, $tags, true);
    } else {
        error_log('No tag suggestions available.');
    }
}

/**
 * Helper function to process tags from OpenAI response
 */
function openai_tag_suggester_process_tags($tags) {
    // If we have a single tag that's longer than expected, it might be multiple tags
    if (count($tags) === 1) {
        $single_tag = $tags[0];
        
        // Check if it contains multiple words (potential multiple tags)
        $words = explode(' ', $single_tag);
        if (count($words) > 3) {
            // Try to split by other delimiters that might be present
            $potential_tags = preg_split('/[;|•]+/', $single_tag);
            
            if (count($potential_tags) > 1) {
                // We found multiple tags using alternate delimiters
                return array_map('trim', $potential_tags);
            }
            
            // Check if it might be a list without proper delimiters
            if (preg_match_all('/\b([A-Z][a-z]+(?:\s+[a-z]+){0,2})\b/', $single_tag, $matches)) {
                if (count($matches[1]) > 1) {
                    return array_map('trim', $matches[1]);
                }
            }
        }
    }
    
    return $tags;
}

/**
 * Debug function to log detailed information about the API response
 */
function openai_tag_suggester_debug_response($content) {
    error_log('=== DEBUG TAG RESPONSE ===');
    error_log('Raw content: "' . $content . '"');
    error_log('Content length: ' . strlen($content));
    error_log('Contains commas: ' . (strpos($content, ',') !== false ? 'YES' : 'NO'));
    error_log('Contains newlines: ' . (strpos($content, "\n") !== false ? 'YES' : 'NO'));
    error_log('Character codes: ' . implode(',', array_map(function($char) {
        return ord($char);
    }, str_split($content))));
    
    // Test different parsing methods
    error_log('Split by commas: ' . print_r(explode(',', $content), true));
    error_log('Split by regex: ' . print_r(preg_split('/,/', $content), true));
    error_log('Split by preg_match_all: ' . print_r(preg_match_all('/([^,]+)/', $content, $matches) ? $matches[1] : [], true));
}

function openai_tag_suggester_get_tags($content, $api_key, $taxonomy) {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $cleaned_content = wp_strip_all_tags($content);
    $cleaned_content = substr($cleaned_content, 0, 2000);
    
    // Get custom prompts from settings
    $system_role = get_option('openai_tag_suggester_system_role');
    $user_role = get_option('openai_tag_suggester_user_role');
    $model = get_option('openai_tag_suggester_model', 'gpt-3.5-turbo');
    
    // Check for taxonomy-specific prompts
    $taxonomy_prompts = get_option('openai_tag_suggester_taxonomy_prompts', array());
    if (!empty($taxonomy_prompts[$taxonomy])) {
        error_log("Using custom prompt for taxonomy: $taxonomy");
        $user_role = $taxonomy_prompts[$taxonomy];
        
        // Extract the number of tags requested from the prompt
        $num_tags_requested = 5; // Default to 5
        if (preg_match('/suggest\s+only\s+(\d+)\s+tag/i', $user_role, $matches)) {
            $num_tags_requested = intval($matches[1]);
            error_log("Detected request for $num_tags_requested tag(s) in custom prompt (only pattern)");
        } elseif (preg_match('/suggest\s+(\d+)\s+tags?/i', $user_role, $matches)) {
            $num_tags_requested = intval($matches[1]);
            error_log("Detected request for $num_tags_requested tag(s) in custom prompt (standard pattern)");
        } elseif (preg_match('/generate\s+(\d+)\s+tags?/i', $user_role, $matches)) {
            $num_tags_requested = intval($matches[1]);
            error_log("Detected request for $num_tags_requested tag(s) in custom prompt (generate pattern)");
        } elseif (preg_match('/exactly\s+(\d+)\s+tags?/i', $user_role, $matches)) {
            $num_tags_requested = intval($matches[1]);
            error_log("Detected request for $num_tags_requested tag(s) in custom prompt (exactly pattern)");
        }
    } else {
        error_log("No custom prompt found for taxonomy: $taxonomy, using default");
        // Modify default prompt based on taxonomy
        $taxonomy_label = openai_tag_suggester_get_available_taxonomies()[$taxonomy];
        $user_role .= " Generate {$taxonomy_label}.";
        $num_tags_requested = 5; // Default for standard prompts
    }
    
    if ($taxonomy === 'hashtag') {
        $user_role .= " Include the # symbol with each suggestion.";
    }
    
    // Add explicit formatting instructions to ensure proper tag separation
    $user_role .= " Format your response as a comma-separated list of tags. Do not include any explanations or additional text.";
    
    $data = array(
        'model' => $model,
        'messages' => array(
            array(
                'role' => 'system',
                'content' => $system_role . " Return ONLY a comma-separated list of $num_tags_requested distinct tag(s) without any additional text, numbering, or formatting."
            ),
            array(
                'role' => 'user',
                'content' => $user_role . " Generate EXACTLY $num_tags_requested separate tag(s), formatted as: tag1" . ($num_tags_requested > 1 ? ", tag2, tag3, etc." : ".") . " Content: $cleaned_content"
            ),
            array(
                'role' => 'assistant',
                'content' => "I'll provide a comma-separated list of $num_tags_requested distinct tag(s)."
            )
        ),
        'temperature' => 0.7,
        'max_tokens' => 250,
        'response_format' => array(
            'type' => 'text'
        )
    );

    // Log the prompt being used
    error_log("Taxonomy: $taxonomy, Prompt: " . $user_role);
    error_log("Requesting exactly $num_tags_requested tag(s)");

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
        $content = $result['choices'][0]['message']['content'];
        error_log('Raw content from API: ' . $content);
        
        // Debug the response
        openai_tag_suggester_debug_response($content);
        
        // First, check if the content is just a single word or phrase
        if (!preg_match('/[,\n]/', $content) && strlen($content) < 50) {
            // This is likely a single tag - if we only requested one tag, just return it
            if ($num_tags_requested === 1) {
                error_log('Single tag requested and received: ' . $content);
                return array(trim($content));
            }
            
            // Otherwise, force a retry with more explicit instructions
            error_log('Content appears to be a single tag, forcing retry');
            
            // Create a new array with this single tag
            $single_tag = array(trim($content));
            
            // Make another API call with more explicit instructions
            $user_role = "Generate EXACTLY $num_tags_requested separate tags for this content. Each tag should be 1-3 words maximum. Format as: tag1" . ($num_tags_requested > 1 ? ", tag2, tag3, etc." : "");
            
            $data = array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => "You are a tagging specialist. Return ONLY a comma-separated list of $num_tags_requested distinct tags. No explanations or other text."
                    ),
                    array(
                        'role' => 'user',
                        'content' => $user_role . ": $cleaned_content"
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 250
            );
            
            $args = array(
                'body' => json_encode($data),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
            );
            
            $retry_response = wp_remote_post($url, $args);
            if (!is_wp_error($retry_response)) {
                $retry_body = wp_remote_retrieve_body($retry_response);
                $retry_result = json_decode($retry_body, true);
                
                error_log('Retry API response: ' . print_r($retry_result, true));
                
                if (isset($retry_result['choices'][0]['message']['content'])) {
                    $retry_content = $retry_result['choices'][0]['message']['content'];
                    error_log('Retry raw content: ' . $retry_content);
                    
                    // Split by commas
                    $retry_tags = array_map('trim', explode(',', $retry_content));
                    
                    // Filter out empty tags
                    $retry_tags = array_filter($retry_tags, function($tag) {
                        return !empty($tag);
                    });
                    
                    // If we got more tags than requested, trim the array
                    if (count($retry_tags) > $num_tags_requested) {
                        error_log('Received more tags than requested, trimming to ' . $num_tags_requested);
                        $retry_tags = array_slice($retry_tags, 0, $num_tags_requested);
                    }
                    
                    if (count($retry_tags) >= 1) {
                        error_log('Successfully got tags from retry: ' . print_r($retry_tags, true));
                        return array_values($retry_tags);
                    }
                }
            }
            
            // If retry failed, return the original single tag
            return $single_tag;
        }
        
        // First, try to extract a list if the response contains numbered items
        if (preg_match_all('/\d+\.\s*([^,\n]+)/', $content, $matches)) {
            error_log('Matched numbered list: ' . print_r($matches[1], true));
            $tags = array_map('trim', $matches[1]);
            // Limit to requested number of tags
            if (count($tags) > $num_tags_requested) {
                $tags = array_slice($tags, 0, $num_tags_requested);
            }
            return $tags;
        }
        
        // Next, try to extract items from a bulleted list - but be careful not to match hyphens in words
        if (preg_match_all('/(?:^|\n)\s*[-*•]\s+([^,\n]+)/', $content, $matches)) {
            error_log('Matched bulleted list: ' . print_r($matches[1], true));
            $tags = array_map('trim', $matches[1]);
            // Limit to requested number of tags
            if (count($tags) > $num_tags_requested) {
                $tags = array_slice($tags, 0, $num_tags_requested);
            }
            return $tags;
        }
        
        // Most common case: comma-separated list (which is what we're getting from the API)
        if (strpos($content, ',') !== false) {
            // Use a more robust regex to split by commas while preserving hyphenated words
            $tags = preg_split('/,\s*/', $content);
            error_log('Split by commas with regex: ' . print_r($tags, true));
            
            // Clean up the tags
            $tags = array_map(function($tag) {
                return trim($tag);
            }, $tags);
            
            // Filter out empty tags
            $tags = array_filter($tags, function($tag) {
                return !empty($tag);
            });
            
            if (count($tags) >= 1) {
                // Limit to requested number of tags
                if (count($tags) > $num_tags_requested) {
                    error_log('Limiting tags to requested number: ' . $num_tags_requested);
                    $tags = array_slice($tags, 0, $num_tags_requested);
                }
                return array_values($tags);
            }
        }
        
        // If no commas, try splitting by newlines
        if (strpos($content, "\n") !== false) {
            $tags = array_map('trim', explode("\n", $content));
            error_log('Split by newlines: ' . print_r($tags, true));
            
            // Filter out empty tags
            $tags = array_filter($tags, function($tag) {
                return !empty($tag);
            });
            
            if (count($tags) >= 1) {
                // Limit to requested number of tags
                if (count($tags) > $num_tags_requested) {
                    $tags = array_slice($tags, 0, $num_tags_requested);
                }
                return array_values($tags);
            }
        }
        
        // If we get here, we need to try more advanced parsing
        $tags = preg_split('/[,\n]+/', $content);
        error_log('Split by commas/newlines: ' . print_r($tags, true));
        
        // Clean up the tags
        $tags = array_map(function($tag) {
            // Remove any numbering, bullets, or quotes
            $tag = preg_replace('/^\d+\.\s*|^[-*•]\s*|^["\']+|["\']+$/', '', $tag);
            return trim($tag);
        }, $tags);
        
        // Filter out empty tags
        $tags = array_filter($tags, function($tag) {
            return !empty($tag);
        });
        
        $result_tags = array_values($tags);
        error_log('Final tags: ' . print_r($result_tags, true));
        
        // Limit to requested number of tags
        if (count($result_tags) > $num_tags_requested) {
            error_log('Final limit of tags to requested number: ' . $num_tags_requested);
            $result_tags = array_slice($result_tags, 0, $num_tags_requested);
        }
        
        // If we still only have one tag, try to split it further
        if (count($result_tags) === 1 && strpos($result_tags[0], ' ') !== false && $num_tags_requested > 1) {
            $single_tag = $result_tags[0];
            error_log('Attempting to split single tag: ' . $single_tag);
            
            // First, check if it's a sentence or paragraph that needs to be parsed
            if (strlen($single_tag) > 50) {
                // This is likely a paragraph or sentence, not a tag
                // Try to extract comma-separated values that might be embedded in text
                if (preg_match_all('/\b([^,.]+(?:\s+[^,.]{1,20}){0,2})[,.]/', $single_tag . ',', $matches)) {
                    $extracted_tags = array_map('trim', $matches[1]);
                    error_log('Extracted tags from text: ' . print_r($extracted_tags, true));
                    if (count($extracted_tags) > 1) {
                        // Limit to requested number of tags
                        if (count($extracted_tags) > $num_tags_requested) {
                            $extracted_tags = array_slice($extracted_tags, 0, $num_tags_requested);
                        }
                        return $extracted_tags;
                    }
                }
                
                // Try to find tags in quotes
                if (preg_match_all('/"([^"]+)"/', $single_tag, $matches)) {
                    $quoted_tags = array_map('trim', $matches[1]);
                    error_log('Extracted quoted tags: ' . print_r($quoted_tags, true));
                    if (count($quoted_tags) > 1) {
                        // Limit to requested number of tags
                        if (count($quoted_tags) > $num_tags_requested) {
                            $quoted_tags = array_slice($quoted_tags, 0, $num_tags_requested);
                        }
                        return $quoted_tags;
                    }
                }
                
                // Last resort: try to split by common separators
                $separators = array(', ', '; ', ' - ', ' | ', ' and ', ' or ');
                foreach ($separators as $separator) {
                    if (strpos($single_tag, $separator) !== false) {
                        $split_tags = array_map('trim', explode($separator, $single_tag));
                        error_log('Split by separator "' . $separator . '": ' . print_r($split_tags, true));
                        if (count($split_tags) > 1) {
                            // Limit to requested number of tags
                            if (count($split_tags) > $num_tags_requested) {
                                $split_tags = array_slice($split_tags, 0, $num_tags_requested);
                            }
                            return $split_tags;
                        }
                    }
                }
            }
            
            // Don't split by spaces unless we're sure it's not a legitimate multi-word tag
            // Instead, let's try to force a better response from OpenAI
        }
        
        // If we still only have one tag and requested more, make another API call with more explicit instructions
        if (count($result_tags) === 1 && $num_tags_requested > 1) {
            error_log('Still only one tag, making another API call with more explicit instructions');
            
            // Modify the prompt to be even more explicit
            $user_role = "Generate EXACTLY $num_tags_requested separate tags for this content. Each tag should be 1-3 words maximum. Format as: tag1, tag2, tag3, etc.";
            
            $data = array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => "You are a tagging specialist. Return ONLY a comma-separated list of $num_tags_requested distinct tags. No explanations or other text."
                    ),
                    array(
                        'role' => 'user',
                        'content' => $user_role . ": $cleaned_content"
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 250
            );
            
            $args = array(
                'body' => json_encode($data),
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
            );
            
            $retry_response = wp_remote_post($url, $args);
            if (!is_wp_error($retry_response)) {
                $retry_body = wp_remote_retrieve_body($retry_response);
                $retry_result = json_decode($retry_body, true);
                
                error_log('Retry API response: ' . print_r($retry_result, true));
                
                if (isset($retry_result['choices'][0]['message']['content'])) {
                    $retry_content = $retry_result['choices'][0]['message']['content'];
                    error_log('Retry raw content: ' . $retry_content);
                    
                    // Split by commas
                    $retry_tags = array_map('trim', explode(',', $retry_content));
                    
                    // Filter out empty tags
                    $retry_tags = array_filter($retry_tags, function($tag) {
                        return !empty($tag);
                    });
                    
                    // Limit to requested number of tags
                    if (count($retry_tags) > $num_tags_requested) {
                        $retry_tags = array_slice($retry_tags, 0, $num_tags_requested);
                    }
                    
                    if (count($retry_tags) >= 1) {
                        error_log('Successfully got tags from retry: ' . print_r($retry_tags, true));
                        return array_values($retry_tags);
                    }
                }
            }
        }
        
        // Process tags with our helper function
        $result_tags = openai_tag_suggester_process_tags($result_tags);
        error_log('Processed tags: ' . print_r($result_tags, true));
        
        // Final check to ensure we're returning the requested number of tags
        if (count($result_tags) > $num_tags_requested) {
            error_log('Final limit of tags to requested number: ' . $num_tags_requested);
            $result_tags = array_slice($result_tags, 0, $num_tags_requested);
        }
        
        return $result_tags;
    }

    return [];
}

// Add meta box to post editor screen
add_action('add_meta_boxes', 'openai_tag_suggester_add_meta_box', 1);
function openai_tag_suggester_add_meta_box() {
    // Add our meta box to posts
    add_meta_box(
        'openai_tag_suggester_meta_box',
        'AI Tag Suggestions',
        'openai_tag_suggester_meta_box_callback',
        'post',
        'side',  // Changed from 'normal' to 'side' to place it in the right sidebar
        'high'   // High priority to ensure it's at the top of the sidebar
    );
    
    // For Classic Editor, we need to ensure the meta box is visible
    if (openai_tag_suggester_is_classic_editor()) {
        // Add a script to ensure the meta box is visible in Classic Editor
        add_action('admin_footer', 'openai_tag_suggester_ensure_metabox_visible');
    } else {
        // For Block Editor, add a script to adjust the width
        add_action('admin_footer', 'openai_tag_suggester_adjust_block_editor_width');
    }
    
    // Debug log
    error_log('OpenAI Tag Suggester: Meta box added with high priority to side panel');
}

// Add function to adjust width in Block Editor
function openai_tag_suggester_adjust_block_editor_width() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('OpenAI Tag Suggester: Adjusting width for Block Editor');
        
        // Add a class to identify Block Editor mode
        $('#openai_tag_suggester_container').addClass('block-editor-mode');
        
        // Add custom CSS for Block Editor
        var css = `
            .edit-post-meta-boxes-area #openai_tag_suggester_meta_box {
                max-width: 300px !important;
                margin-left: auto !important;
                margin-right: auto !important;
                border: 1px solid #005035 !important;
            }
            
            .edit-post-meta-boxes-area #openai_tag_suggester_meta_box .inside {
                padding: 8px !important;
                margin: 0 !important;
            }
            
            .edit-post-meta-boxes-area .taxonomy-selector select {
                max-width: 100% !important;
                font-size: 12px !important;
            }
            
            .edit-post-meta-boxes-area #openai_tag_suggester_meta_box .components-panel__header {
                background-color: #005035 !important;
            }
            
            .edit-post-meta-boxes-area #openai_tag_suggester_meta_box .components-panel__header h2 {
                color: #ffffff !important;
            }
            
            .edit-post-meta-boxes-area #openai_tag_suggester_button,
            .edit-post-meta-boxes-area .add-selected-tags {
                background-color: #005035 !important;
                border-color: #003824 !important;
                color: #ffffff !important;
                width: 100% !important;
                text-align: center !important;
            }
            
            .edit-post-meta-boxes-area #openai_tag_suggester_button:hover,
            .edit-post-meta-boxes-area .add-selected-tags:hover {
                background-color: #006a46 !important;
                border-color: #005035 !important;
            }
            
            .edit-post-meta-boxes-area #openai_tag_suggester_button:focus,
            .edit-post-meta-boxes-area .add-selected-tags:focus {
                box-shadow: 0 0 0 1px #ffffff, 0 0 0 3px #005035 !important;
            }
            
            .edit-post-meta-boxes-area .tag-controls {
                display: flex !important;
                gap: 4px !important;
                width: 100% !important;
            }
            
            .edit-post-meta-boxes-area .tag-controls button {
                flex: 0 0 48% !important;
                color: #005035 !important;
                border-color: #005035 !important;
                text-align: center !important;
                padding: 0 !important;
                font-size: 11px !important;
            }
            
            .edit-post-meta-boxes-area .tag-controls button:hover {
                background-color: #f0f7f4 !important;
                border-color: #005035 !important;
            }
        `;
        
        // Add the CSS to the page
        $('<style>').text(css).appendTo('head');
    });
    </script>
    <?php
}

function openai_tag_suggester_meta_box_callback($post) {
    wp_nonce_field('openai_tag_suggester_nonce', 'openai_tag_suggester_nonce');
    
    // Add debug information
    error_log('OpenAI Tag Suggester: Meta box callback called for post ID ' . $post->ID);
    
    // Add a class to identify if we're in Classic Editor
    $editor_class = openai_tag_suggester_is_classic_editor() ? 'classic-editor-mode' : 'block-editor-mode';
    error_log('OpenAI Tag Suggester: Editor class: ' . $editor_class);
    
    echo '<div id="openai_tag_suggester_container" class="ai-tag-suggester ' . $editor_class . '">';
    
    // Instructions for users
    echo '<p class="howto">Generate AI-powered tag suggestions based on your content.</p>';
    
    // Taxonomy selector
    $enabled_taxonomies = get_option('openai_tag_suggester_enabled_taxonomies', array('post_tag'));
    $taxonomy_prompts = get_option('openai_tag_suggester_taxonomy_prompts', array());
    error_log('OpenAI Tag Suggester: Enabled taxonomies: ' . print_r($enabled_taxonomies, true));
    
    echo '<div class="taxonomy-selector" style="margin-bottom: 10px;">';
    echo '<label for="openai_tag_suggester_taxonomy" class="howto">Select taxonomy:</label>';
    echo '<select id="openai_tag_suggester_taxonomy" name="openai_tag_suggester_taxonomy" class="widefat">';
    
    $available_taxonomies = openai_tag_suggester_get_available_taxonomies();
    error_log('OpenAI Tag Suggester: Available taxonomies: ' . print_r($available_taxonomies, true));
    
    foreach ($available_taxonomies as $tax_name => $tax_label) {
        if (in_array($tax_name, $enabled_taxonomies)) {
            $selected = ($tax_name === 'post_tag') ? 'selected' : '';
            $has_custom_prompt = !empty($taxonomy_prompts[$tax_name]) ? ' ★' : '';
            echo '<option value="' . esc_attr($tax_name) . '" ' . $selected . '>' . 
                 esc_html($tax_label) . $has_custom_prompt . '</option>';
        }
    }
    echo '</select>';
    echo '<p class="taxonomy-prompt-info howto" style="font-size: 11px; margin-top: 3px;">★ = Has custom prompt</p>';
    echo '</div>';
    
    // Generate button
    echo '<button type="button" id="openai_tag_suggester_button" name="openai_tag_suggester_button" ';
    echo 'class="button button-primary" style="width: 100%; margin-bottom: 10px;">';
    echo 'Generate Tag Suggestions</button>';
    
    // Results area
    echo '<div id="openai_tag_suggester_results" class="tag-results-container"></div>';
    
    // Add debug info for Classic Editor only in debug mode
    if (openai_tag_suggester_is_classic_editor() && defined('WP_DEBUG') && WP_DEBUG) {
        echo '<div class="classic-editor-info" style="margin-top: 10px; font-size: 11px; color: #666;">';
        echo 'Classic Editor detected. Tags will be added to the Tags meta box.';
        echo '</div>';
    }
    
    echo '</div>';
    
    // Add visible debug info only in debug mode
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo '<div class="debug-info" style="margin-top: 8px; padding: 5px; background: #f8f8f8; border: 1px solid #ddd; font-size: 11px;">';
        echo '<p><strong>Debug Info:</strong></p>';
        echo '<p>Editor Type: ' . ($editor_class == 'classic-editor-mode' ? 'Classic Editor' : 'Block Editor') . '</p>';
        echo '<p>Meta Box ID: openai_tag_suggester_meta_box</p>';
        echo '<p>Enabled Taxonomies: ' . implode(', ', $enabled_taxonomies) . '</p>';
        
        // Show custom prompts in debug mode
        if (!empty($taxonomy_prompts)) {
            echo '<p>Custom Prompts: ';
            foreach ($taxonomy_prompts as $tax => $prompt) {
                if (isset($available_taxonomies[$tax])) {
                    echo '<br>- ' . esc_html($available_taxonomies[$tax]) . ': Yes';
                }
            }
            echo '</p>';
        }
        
        echo '</div>';
    }
}

// Function to ensure the meta box is visible in Classic Editor
function openai_tag_suggester_ensure_metabox_visible() {
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('OpenAI Tag Suggester: Ensuring meta box visibility in Classic Editor');
        
        // Force show our meta box
        $('#openai_tag_suggester_meta_box').show();
        $('#openai_tag_suggester_meta_box-hide').prop('checked', false);
        
        // If the meta box is hidden by screen options, unhide it
        if ($('#openai_tag_suggester_meta_box').css('display') === 'none') {
            $('#openai_tag_suggester_meta_box').show();
        }
        
        // Add a more aggressive approach to ensure visibility
        setTimeout(function() {
            console.log('OpenAI Tag Suggester: Delayed visibility check');
            $('#openai_tag_suggester_meta_box').css({
                'display': 'block !important',
                'visibility': 'visible !important',
                'opacity': '1 !important'
            });
            
            // Also try to add it to the page if it's missing
            if ($('#openai_tag_suggester_meta_box').length === 0) {
                console.log('OpenAI Tag Suggester: Meta box not found, attempting to add it');
                
                // Create a basic version of the meta box
                var metaBox = $('<div id="openai_tag_suggester_meta_box" class="postbox">' +
                    '<h2 class="hndle ui-sortable-handle"><span>AI Tag Suggestions</span></h2>' +
                    '<div class="inside">' +
                    '<p>Please refresh the page to use the AI Tag Suggester.</p>' +
                    '</div></div>');
                
                // Add it to the side meta box container
                $('#side-sortables').append(metaBox);
            }
        }, 1000);
    });
    </script>
    <?php
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
        plugin_dir_url(__FILE__) . 'openai-tag-suggester-admin.css',
        array(),
        '1.0.7'  // Increment version to bust cache
    );
    
    // Enqueue our custom JavaScript file
    wp_enqueue_script(
        'openai-tag-suggester-admin', 
        plugin_dir_url(__FILE__) . 'openai-tag-suggester-admin.js', 
        array('jquery'), 
        '1.0.4',  // Increment version to bust cache
        true  // Load in footer
    );
    
    // Pass PHP variables to JavaScript
    global $post;
    $taxonomy_prompts = get_option('openai_tag_suggester_taxonomy_prompts', array());
    $taxonomies_with_prompts = array_keys($taxonomy_prompts);
    
    wp_localize_script(
        'openai-tag-suggester-admin',
        'openaiTagSuggester',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_id' => $post->ID,
            'nonce' => wp_create_nonce('openai_tag_suggester_nonce'),
            'is_classic_editor' => openai_tag_suggester_is_classic_editor(),
            'taxonomies_with_prompts' => $taxonomies_with_prompts
        )
    );
    
    // Add inline script to ensure initialization
    wp_add_inline_script('openai-tag-suggester-admin', '
        jQuery(document).ready(function($) {
            console.log("OpenAI Tag Suggester: Inline script running");
            
            // Force initialization after a short delay
            setTimeout(function() {
                if (typeof openaiTagSuggester !== "undefined") {
                    console.log("OpenAI Tag Suggester: Triggering initialization from inline script");
                    $(document).trigger("openai_tag_suggester_init");
                }
            }, 1000);
        });
    ');
}

/**
 * Check if the current editor is the Classic Editor
 */
function openai_tag_suggester_is_classic_editor() {
    // Check if the Classic Editor plugin is active
    if (function_exists('classic_editor_init')) {
        // Check if the user has selected to use the Classic Editor
        $editor_option = get_option('classic-editor-replace');
        if ($editor_option === 'classic' || $editor_option === 'replace') {
            return true;
        }
        
        // Check if the current post is being edited with Classic Editor
        if (isset($_GET['classic-editor'])) {
            return true;
        }
    }
    
    // Check if the Gutenberg plugin is NOT active and WordPress version is < 5.0
    if (!function_exists('gutenberg_init') && version_compare($GLOBALS['wp_version'], '5.0', '<')) {
        return true;
    }
    
    return false;
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

    // Get content sources from settings
    $content_sources = get_option('openai_tag_suggester_content_sources', array('post_content'));
    error_log('Content sources: ' . print_r($content_sources, true));
    
    // Initialize combined content
    $combined_content = '';
    
    // Get post object
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Invalid post ID');
        return;
    }
    
    // Process each content source
    foreach ($content_sources as $source) {
        if ($source === 'post_content') {
            // Get post content - either from the request or from the database
            if (isset($_POST['content']) && !empty($_POST['content'])) {
                // Use content sent from the client (useful for unsaved changes)
                $post_content = wp_kses_post($_POST['content']);
                error_log('Using content from request (' . strlen($post_content) . ' chars)');
            } else {
                // Fall back to getting content from the database
                $post_content = $post->post_content;
                error_log('Using content from database (' . strlen($post_content) . ' chars)');
            }
            
            // Add to combined content with a heading
            $combined_content .= "POST CONTENT:\n" . $post_content . "\n\n";
        } else {
            // This is a meta field
            $meta_value = openai_tag_suggester_get_faculty_meta($post_id, $source);
            
            if (!empty($meta_value)) {
                // Sanitize meta value based on its type
                if (is_array($meta_value) || is_object($meta_value)) {
                    // Convert arrays and objects to a readable string format
                    $meta_value = print_r($meta_value, true);
                    // Sanitize the resulting string
                    $meta_value = sanitize_textarea_field($meta_value);
                } elseif (is_numeric($meta_value)) {
                    // Keep numeric values as is
                    $meta_value = (string) $meta_value;
                } elseif (is_string($meta_value)) {
                    // Sanitize string values
                    // Check if it looks like HTML content
                    if (strpos($meta_value, '<') !== false && strpos($meta_value, '>') !== false) {
                        // It might be HTML, use wp_kses_post to preserve structure but remove unsafe elements
                        $meta_value = wp_kses_post($meta_value);
                        // Convert to plain text for better AI processing
                        $meta_value = wp_strip_all_tags($meta_value);
                    } else {
                        // Regular string, use standard sanitization
                        $meta_value = sanitize_textarea_field($meta_value);
                    }
                } else {
                    // For any other type, convert to string and sanitize
                    $meta_value = sanitize_textarea_field((string) $meta_value);
                }
                
                // Limit meta field content length to prevent excessive API usage
                $max_meta_length = 5000; // 5000 characters max per meta field
                if (strlen($meta_value) > $max_meta_length) {
                    $meta_value = substr($meta_value, 0, $max_meta_length) . '... [truncated]';
                    error_log('Meta field ' . $source . ' truncated from ' . strlen($meta_value) . ' to ' . $max_meta_length . ' chars');
                }
                
                // Add to combined content with a heading
                $combined_content .= "META FIELD (" . $source . "):\n" . $meta_value . "\n\n";
                error_log('Added meta field ' . $source . ' (' . strlen($meta_value) . ' chars)');
            }
        }
    }
    
    // If no content was found, return an error
    if (empty($combined_content)) {
        wp_send_json_error('No content found from selected sources');
        return;
    }
    
    // Get API key
    $api_key = get_option('openai_tag_suggester_api_key');
    if (!$api_key) {
        wp_send_json_error('API key not set');
        return;
    }

    // Check if we're using a custom prompt for this taxonomy
    $taxonomy_prompts = get_option('openai_tag_suggester_taxonomy_prompts', array());
    $using_custom_prompt = !empty($taxonomy_prompts[$taxonomy]);
    $taxonomy_label = openai_tag_suggester_get_available_taxonomies()[$taxonomy];

    try {
        // Make sure we don't have any output before sending JSON
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $tags = openai_tag_suggester_get_tags($combined_content, $api_key, $taxonomy);
        if ($tags) {
            wp_send_json_success(array(
                'suggested' => $tags,
                'existing' => wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names')),
                'using_custom_prompt' => $using_custom_prompt,
                'taxonomy_label' => $taxonomy_label,
                'content_sources' => $content_sources
            ));
        } else {
            wp_send_json_error('Failed to generate tags');
        }
    } catch (Exception $e) {
        wp_send_json_error('Error: ' . $e->getMessage());
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
    // Get all taxonomies that are registered in WordPress
    $all_taxonomies = get_taxonomies(array('show_ui' => true), 'objects');
    
    // Create an array to store the taxonomies
    $available_taxonomies = array();
    
    // Loop through each taxonomy and add it to our array
    foreach ($all_taxonomies as $taxonomy) {
        // Skip internal WordPress taxonomies like 'link_category'
        if ($taxonomy->name === 'link_category') {
            continue;
        }
        
        // Add the taxonomy to our array with the label as the value
        $available_taxonomies[$taxonomy->name] = $taxonomy->label;
    }
    
    // If no taxonomies were found, add post_tag as a fallback
    if (empty($available_taxonomies)) {
        $available_taxonomies['post_tag'] = 'Tags';
    }
    
    return $available_taxonomies;
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

// Add sanitization callback for taxonomy prompts
function openai_tag_suggester_sanitize_taxonomy_prompts($prompts) {
    error_log('Sanitizing taxonomy prompts: ' . print_r($prompts, true));
    
    if (!is_array($prompts)) {
        error_log('Taxonomy prompts is not an array, defaulting to empty array');
        return array();
    }
    
    // Get available taxonomies
    $available_taxonomies = array_keys(openai_tag_suggester_get_available_taxonomies());
    
    // Filter out any invalid taxonomy keys
    $sanitized_prompts = array();
    foreach ($prompts as $taxonomy => $prompt) {
        if (in_array($taxonomy, $available_taxonomies)) {
            $sanitized_prompts[$taxonomy] = sanitize_textarea_field($prompt);
        }
    }
    
    error_log('Sanitized taxonomy prompts: ' . print_r($sanitized_prompts, true));
    
    return $sanitized_prompts;
}

// Sanitize content sources
function openai_tag_suggester_sanitize_content_sources($sources) {
    if (!is_array($sources)) {
        return array('post_content');
    }
    
    $sanitized = array();
    foreach ($sources as $source) {
        $sanitized[] = sanitize_text_field($source);
    }
    
    // Ensure at least post_content is selected
    if (empty($sanitized)) {
        $sanitized[] = 'post_content';
    }
    
    return $sanitized;
}

// Sanitize post types to scan
function openai_tag_suggester_sanitize_post_types($post_types) {
    if (!is_array($post_types)) {
        return array();
    }
    
    $sanitized = array();
    $valid_post_types = get_post_types(array('public' => true), 'names');
    
    foreach ($post_types as $post_type) {
        $post_type = sanitize_text_field($post_type);
        if (in_array($post_type, $valid_post_types)) {
            $sanitized[] = $post_type;
        }
    }
    
    return $sanitized;
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
// Add a body class for Classic Editor
add_filter('admin_body_class', 'openai_tag_suggester_add_admin_body_class');
function openai_tag_suggester_add_admin_body_class($classes) {
    // Check if we're on the post edit screen
    $screen = get_current_screen();
    if ($screen && $screen->base === 'post' && openai_tag_suggester_is_classic_editor()) {
        $classes .= ' using-classic-editor';
    }
    return $classes;
}

// Add an admin notice to help debug
add_action('admin_notices', 'openai_tag_suggester_admin_notice');
function openai_tag_suggester_admin_notice() {
    // Only show in debug mode
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $screen = get_current_screen();
    
    // Only show on post edit screen
    if (!$screen || $screen->base !== 'post') {
        return;
    }
    
    $is_classic = openai_tag_suggester_is_classic_editor() ? 'Yes' : 'No';
    
    echo '<div class="notice notice-info is-dismissible" style="padding: 8px 12px; font-size: 13px;">';
    echo '<p><strong>OpenAI Tag Suggester:</strong> ';
    echo 'Using ' . ($is_classic ? 'Classic Editor' : 'Block Editor') . ' | ';
    echo 'Screen: ' . $screen->base . ' | ';
    echo 'Post Type: ' . $screen->post_type . '</p>';
    echo '</div>';
}

// Ensure our meta box is not hidden by default in Classic Editor
add_filter('default_hidden_meta_boxes', 'openai_tag_suggester_show_meta_box', 10, 2);
function openai_tag_suggester_show_meta_box($hidden, $screen) {
    // Only modify for post edit screen
    if ($screen->base === 'post' && openai_tag_suggester_is_classic_editor()) {
        // Remove our meta box from the hidden list if it's there
        $hidden = array_diff($hidden, array('openai_tag_suggester_meta_box'));
    }
    return $hidden;
}

// Add direct JavaScript injection to force meta box visibility
add_action('admin_footer', 'openai_tag_suggester_force_metabox_visibility');
function openai_tag_suggester_force_metabox_visibility() {
    $screen = get_current_screen();
    
    // Only run on post edit screen
    if (!$screen || $screen->base !== 'post') {
        return;
    }
    
    // Get the enabled taxonomies
    $enabled_taxonomies = get_option('openai_tag_suggester_enabled_taxonomies', array('post_tag'));
    $taxonomy_options = '';
    
    foreach (openai_tag_suggester_get_available_taxonomies() as $tax_name => $tax_label) {
        if (in_array($tax_name, $enabled_taxonomies)) {
            $selected = ($tax_name === 'post_tag') ? 'selected' : '';
            $taxonomy_options .= '<option value="' . esc_attr($tax_name) . '" ' . $selected . '>' . 
                 esc_html($tax_label) . '</option>';
        }
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        console.log('OpenAI Tag Suggester: Force visibility script running');
        
        // Force the meta box to be visible with !important styles
        var css = `
            #openai_tag_suggester_meta_box {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                background-color: #f8f8f8 !important;
                border: 1px solid #005035 !important;
                margin-top: 20px !important;
            }
            #openai_tag_suggester_meta_box h2 {
                background-color: #005035 !important;
                color: #ffffff !important;
                font-weight: bold !important;
            }
            #openai_tag_suggester_meta_box .inside {
                padding: 10px !important;
            }
            #openai_tag_suggester_button,
            .add-selected-tags {
                background-color: #005035 !important;
                border-color: #003824 !important;
                color: #ffffff !important;
            }
            #openai_tag_suggester_button:hover,
            .add-selected-tags:hover {
                background-color: #006a46 !important;
                border-color: #005035 !important;
            }
            .tag-controls {
                display: flex !important;
                gap: 5px !important;
                width: 100% !important;
            }
            .tag-controls button {
                flex: 0 0 48% !important;
                color: #005035 !important;
                border-color: #005035 !important;
                text-align: center !important;
                padding: 0 !important;
                font-size: 11px !important;
            }
            .tag-controls button:hover {
                background-color: #f0f7f4 !important;
                border-color: #005035 !important;
            }
        `;
        
        // Add the CSS to the page
        $('<style>').text(css).appendTo('head');
        
        // Check if the meta box exists
        if ($('#openai_tag_suggester_meta_box').length === 0) {
            console.log('OpenAI Tag Suggester: Meta box not found, creating it');
            
            // Create a complete version of the meta box with all necessary content
            var metaBox = $('<div id="openai_tag_suggester_meta_box" class="postbox">' +
                '<h2 class="hndle ui-sortable-handle"><span>AI Tag Suggestions</span></h2>' +
                '<div class="inside">' +
                '<div id="openai_tag_suggester_container" class="ai-tag-suggester">' +
                '<p class="howto">Generate AI-powered tag suggestions based on your content.</p>' +
                '<div class="taxonomy-selector" style="margin-bottom: 10px;">' +
                '<label for="openai_tag_suggester_taxonomy" class="howto">Select taxonomy:</label>' +
                '<select id="openai_tag_suggester_taxonomy" name="openai_tag_suggester_taxonomy" class="widefat">' +
                '<?php echo $taxonomy_options; ?>' +
                '</select>' +
                '</div>' +
                '<button type="button" id="openai_tag_suggester_button" name="openai_tag_suggester_button" ' +
                'class="button button-primary" style="width: 100%; margin-bottom: 10px;">' +
                'Generate Tag Suggestions</button>' +
                '<div id="openai_tag_suggester_results" class="tag-results-container"></div>' +
                '</div>' +
                '</div></div>');
            
            // Add it to the side meta box container, right after the publish box
            var $publishBox = $('#submitdiv');
            if ($publishBox.length) {
                metaBox.insertAfter($publishBox);
            } else {
                // Fallback to adding it to the side sortables container
                $('#side-sortables').prepend(metaBox);
            }
            
            // Make sure it's visible
            $('#openai_tag_suggester_meta_box').show();
            
            // Trigger a custom event to notify our script that the meta box has been created
            $(document).trigger('openai_tag_suggester_metabox_created');
        } else {
            console.log('OpenAI Tag Suggester: Meta box found, ensuring visibility');
            $('#openai_tag_suggester_meta_box').show();
            
            // Move the meta box to the side panel if it's not already there
            var $metaBox = $('#openai_tag_suggester_meta_box');
            var $publishBox = $('#submitdiv');
            
            if ($publishBox.length && !$metaBox.parent().is('#side-sortables')) {
                $metaBox.insertAfter($publishBox);
            }
            
            // Check if the inside content exists
            if ($('#openai_tag_suggester_container').length === 0) {
                console.log('OpenAI Tag Suggester: Container not found, creating content');
                
                // Create the content
                var content = '<div id="openai_tag_suggester_container" class="ai-tag-suggester">' +
                    '<p class="howto">Generate AI-powered tag suggestions based on your content.</p>' +
                    '<div class="taxonomy-selector" style="margin-bottom: 10px;">' +
                    '<label for="openai_tag_suggester_taxonomy" class="howto">Select taxonomy:</label>' +
                    '<select id="openai_tag_suggester_taxonomy" name="openai_tag_suggester_taxonomy" class="widefat">' +
                    '<?php echo $taxonomy_options; ?>' +
                    '</select>' +
                    '</div>' +
                    '<button type="button" id="openai_tag_suggester_button" name="openai_tag_suggester_button" ' +
                    'class="button button-primary" style="width: 100%; margin-bottom: 10px;">' +
                    'Generate Tag Suggestions</button>' +
                    '<div id="openai_tag_suggester_results" class="tag-results-container"></div>' +
                    '</div>';
                
                // Add the content to the meta box
                $('#openai_tag_suggester_meta_box .inside').html(content);
                
                // Trigger a custom event to notify our script that the content has been created
                $(document).trigger('openai_tag_suggester_content_created');
            }
        }
        
        // Also ensure it's not hidden by screen options
        $('#openai_tag_suggester_meta_box-hide').prop('checked', false);
    });
    </script>
    <?php
}

// Add admin styles
add_action('admin_enqueue_scripts', 'openai_tag_suggester_admin_styles');
function openai_tag_suggester_admin_styles($hook) {
    // Only load on post edit screen and our settings page
    if ($hook !== 'post.php' && $hook !== 'post-new.php' && $hook !== 'settings_page_openai-tag-suggester') {
        return;
    }
    
    wp_enqueue_style(
        'openai-tag_suggester-admin',
        plugins_url('openai-tag-suggester-admin.css', __FILE__),
        array(),
        '1.1.0'  // Increment version to bust cache
    );
    
    // Add inline styles for taxonomy settings
    if ($hook === 'settings_page_openai-tag-suggester') {
        $custom_css = "
            /* Taxonomy filter */
            .taxonomy-filter {
                margin-bottom: 20px;
            }
            
            #taxonomy-search {
                padding: 8px;
                font-size: 14px;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: inset 0 1px 2px rgba(0,0,0,.07);
            }
            
            /* Taxonomy groups */
            .taxonomy-group {
                margin-bottom: 30px;
                border: 1px solid #e5e5e5;
                background-color: #fff;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                border-radius: 4px;
                overflow: hidden;
            }
            
            .taxonomy-group h3 {
                margin: 0;
                padding: 12px 15px;
                background-color: #f9f9f9;
                border-bottom: 1px solid #e5e5e5;
                font-size: 14px;
                color: #23282d;
            }
            
            /* Taxonomy items */
            .taxonomy-item {
                margin: 0;
                padding: 15px;
                background-color: #fff;
                border-bottom: 1px solid #f1f1f1;
            }
            
            .taxonomy-item:last-child {
                border-bottom: none;
            }
            
            .taxonomy-item:hover {
                background-color: #f9f9f9;
            }
            
            .taxonomy-checkbox-label {
                font-weight: 600;
                font-size: 14px;
                margin-bottom: 10px;
                display: block;
                cursor: pointer;
            }
            
            .taxonomy-name {
                color: #666;
                font-weight: normal;
                font-size: 12px;
            }
            
            .taxonomy-prompt-container {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #f1f1f1;
                background-color: #f9f9f9;
                padding: 10px;
                border-radius: 4px;
            }
            
            .taxonomy-prompt-container textarea {
                border: 1px solid #ddd;
                box-shadow: inset 0 1px 2px rgba(0,0,0,.07);
                padding: 8px;
                font-size: 13px;
            }
            
            .taxonomy-prompt-container .description {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }
            
            /* Debug info */
            .taxonomy-debug {
                margin-top: 20px;
                padding: 10px;
                background-color: #f0f0f0;
                border: 1px solid #ddd;
                display: none; /* Hide by default, can be shown for debugging */
            }
            
            /* Responsive adjustments */
            @media screen and (max-width: 782px) {
                .taxonomy-item {
                    padding: 12px 10px;
                }
                
                .taxonomy-checkbox-label {
                    font-size: 13px;
                }
                
                .taxonomy-prompt-container {
                    padding: 8px;
                }
            }
            
            /* Make sure the widefat class works properly */
            .widefat {
                width: 100%;
                max-width: 100%;
            }
        ";
        wp_add_inline_style('openai-tag_suggester-admin', $custom_css);
    }
}

// AJAX handler for clearing meta fields cache
add_action('wp_ajax_openai_tag_suggester_clear_cache', 'openai_tag_suggester_clear_cache_ajax');
function openai_tag_suggester_clear_cache_ajax() {
    // Verify nonce
    check_ajax_referer('openai_tag_suggester_clear_cache', 'nonce');
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    // Clear the cache
    openai_tag_suggester_clear_meta_fields_cache();
    
    // Send success response
    wp_send_json_success(array('message' => 'Meta fields cache cleared successfully'));
}

// Clear meta fields cache when a new post type is registered
add_action('registered_post_type', 'openai_tag_suggester_clear_meta_fields_cache');

// Clear meta fields cache when a post meta is added or updated
add_action('added_post_meta', 'openai_tag_suggester_maybe_clear_meta_fields_cache', 10, 4);
add_action('updated_post_meta', 'openai_tag_suggester_maybe_clear_meta_fields_cache', 10, 4);

// Only clear cache for new meta keys to avoid clearing too often
function openai_tag_suggester_maybe_clear_meta_fields_cache($meta_id, $object_id, $meta_key, $meta_value) {
    // Skip internal meta keys
    if (substr($meta_key, 0, 1) === '_') {
        return;
    }
    
    // Get cached meta fields
    $cached_meta_fields = get_transient('openai_tag_suggester_meta_fields_cache');
    
    // If this is a new meta key not in our cache, clear the cache
    if (is_array($cached_meta_fields) && !in_array($meta_key, $cached_meta_fields)) {
        openai_tag_suggester_clear_meta_fields_cache();
    }
}

// AJAX handler for scanning meta fields
add_action('wp_ajax_openai_tag_suggester_scan_meta_fields', 'openai_tag_suggester_scan_meta_fields_ajax');
function openai_tag_suggester_scan_meta_fields_ajax() {
    // Start output buffering to catch any unexpected output
    ob_start();
    
    try {
        // Verify nonce
        check_ajax_referer('openai_tag_suggester_scan_meta_fields', 'nonce');
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        // Clear the cache first
        openai_tag_suggester_clear_meta_fields_cache();
        
        // Set force scan flag
        $_POST['force_scan'] = true;
        
        // Enable debug mode for this scan
        $original_debug_mode = get_option('openai_tag_suggester_meta_debug_mode', false);
        update_option('openai_tag_suggester_meta_debug_mode', true);
        
        // Log start of scan
        error_log('Starting meta fields scan via AJAX');
        
        // Perform the scan
        $meta_fields = openai_tag_suggester_get_meta_fields();
        
        // Restore original debug mode setting
        update_option('openai_tag_suggester_meta_debug_mode', $original_debug_mode);
        
        // Get the debug log
        $debug_log = get_option('openai_tag_suggester_meta_debug_log', array());
        
        // Log completion
        error_log('Meta fields scan completed via AJAX, found ' . count($meta_fields) . ' fields');
        
        // Get any output that might have been generated
        $unexpected_output = ob_get_clean();
        
        // Send success response
        wp_send_json_success(array(
            'message' => 'Meta fields scan completed successfully',
            'count' => count($meta_fields),
            'fields' => $meta_fields,
            'debug_log' => $debug_log,
            'unexpected_output' => $unexpected_output
        ));
    } catch (Exception $e) {
        // Get any output that might have been generated
        $unexpected_output = ob_get_clean();
        
        // Log the error
        error_log('Error in meta fields scan: ' . $e->getMessage());
        
        // Send error response
        wp_send_json_error(array(
            'message' => 'Error scanning meta fields: ' . $e->getMessage(),
            'unexpected_output' => $unexpected_output
        ));
    }
}

// Function to get faculty meta field value, handling underscore prefixes
function openai_tag_suggester_get_faculty_meta($post_id, $meta_key) {
    // Try without underscore first
    $meta_value = get_post_meta($post_id, $meta_key, true);
    
    // If empty and this is likely a faculty meta field, try with underscore
    if (empty($meta_value) && (strpos($meta_key, 'faculty_') === 0 || strpos($meta_key, 'search_') === 0)) {
        $meta_value = get_post_meta($post_id, '_' . $meta_key, true);
    }
    
    return $meta_value;
}
?>