<?php
/*
Plugin Name: OpenAI Tag Suggester
Description: Suggests tags for posts using the OpenAI API.
Version: 1.2.0
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
        'default' => 'Suggest 3-15 tags for the following faculty profile. There should NOT be any tag suggestions for places, for position titles, or for names of people. Tags should not be longer than 2-3 words. Format your response as a simple comma-separated list of tags without any additional text, numbering, or explanations. Example format: "tag1, tag2, tag3"'
    ));

    // Taxonomy-specific prompts setting
    register_setting(
        'openai_tag_suggester_options', 
        'openai_tag_suggester_taxonomy_prompts',
        array(
            'type' => 'array',
            'default' => array(),
            'sanitize_callback' => 'openai_tag_suggester_sanitize_taxonomy_prompts'
        )
    );

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
        'openai_tag_suggester_model',
        'Model',
        'openai_tag_suggester_model_field',
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
    echo '<div class="taxonomy-section-description">';
    echo '<p>Select which taxonomies should be available for tag suggestions. The plugin will detect all taxonomies registered on your site.</p>';
    echo '<p>For each enabled taxonomy, you can optionally create a custom prompt that will be used specifically when generating suggestions for that taxonomy.</p>';
    echo '<p><strong>Tips:</strong></p>';
    echo '<ul style="list-style-type: disc; margin-left: 20px;">';
    echo '<li>Use the search box to quickly find specific taxonomies</li>';
    echo '<li>Taxonomies are grouped by post type for easier navigation</li>';
    echo '<li>Custom prompts can include specific instructions for each taxonomy (e.g., "Suggest 5 tags related to technology")</li>';
    echo '<li>Leave the custom prompt field empty to use the default prompt</li>';
    echo '</ul>';
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

    // Get post content - either from the request or from the database
    $post_content = '';
    if (isset($_POST['content']) && !empty($_POST['content'])) {
        // Use content sent from the client (useful for unsaved changes)
        $post_content = wp_kses_post($_POST['content']);
        error_log('Using content from request (' . strlen($post_content) . ' chars)');
    } else {
        // Fall back to getting content from the database
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Invalid post ID');
            return;
        }
        $post_content = $post->post_content;
        error_log('Using content from database (' . strlen($post_content) . ' chars)');
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
        
        $tags = openai_tag_suggester_get_tags($post_content, $api_key, $taxonomy);
        if ($tags) {
            wp_send_json_success(array(
                'suggested' => $tags,
                'existing' => wp_get_object_terms($post_id, $taxonomy, array('fields' => 'names')),
                'using_custom_prompt' => $using_custom_prompt,
                'taxonomy_label' => $taxonomy_label
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
?>