jQuery(document).ready(function($) {
    console.log('OpenAI Tag Suggester: JavaScript loaded');
    
    // Initialize the tag suggester
    function initTagSuggester() {
        console.log('OpenAI Tag Suggester: Initializing');
        
        // Debug info
        if (typeof openaiTagSuggester !== 'undefined') {
            console.log('OpenAI Tag Suggester config:', openaiTagSuggester);
        } else {
            console.error('OpenAI Tag Suggester: Configuration object not found!');
            return; // Exit if the config is missing
        }
        
        // Check if the meta box exists
        if ($('#openai_tag_suggester_meta_box').length === 0) {
            console.log('OpenAI Tag Suggester: Meta box not found in initial check, waiting for dynamic creation');
            
            // Set an interval to check for the meta box being added dynamically
            var checkInterval = setInterval(function() {
                if ($('#openai_tag_suggester_meta_box').length > 0) {
                    console.log('OpenAI Tag Suggester: Meta box found after dynamic creation');
                    clearInterval(checkInterval);
                    setupMetaBox();
                }
            }, 500);
            
            // Set a timeout to stop checking after 10 seconds
            setTimeout(function() {
                clearInterval(checkInterval);
                console.error('OpenAI Tag Suggester: Timed out waiting for meta box');
            }, 10000);
        } else {
            console.log('OpenAI Tag Suggester: Meta box found in initial check');
            setupMetaBox();
        }
        
        // Listen for custom events from the force visibility script
        $(document).on('openai_tag_suggester_metabox_created', function() {
            console.log('OpenAI Tag Suggester: Received metabox_created event');
            setupMetaBox();
        });
        
        $(document).on('openai_tag_suggester_content_created', function() {
            console.log('OpenAI Tag Suggester: Received content_created event');
            setupMetaBox();
        });
    }
    
    // Setup the meta box once it's available
    function setupMetaBox() {
        // Make sure our meta box is visible
        $('#openai_tag_suggester_meta_box').show();
        
        // Check if the button exists inside the meta box
        if ($('#openai_tag_suggester_button').length === 0) {
            console.log('OpenAI Tag Suggester: Button not found, recreating meta box content');
            
            // Get the inside div of the meta box
            var $inside = $('#openai_tag_suggester_meta_box .inside');
            if ($inside.length > 0) {
                // Create the content for the meta box
                var content = '<div id="openai_tag_suggester_container" class="ai-tag-suggester">';
                content += '<p class="howto">Generate AI-powered tag suggestions based on your content.</p>';
                
                // Taxonomy selector
                content += '<div class="taxonomy-selector" style="margin-bottom: 10px;">';
                content += '<label for="openai_tag_suggester_taxonomy" class="howto">Select taxonomy:</label>';
                content += '<select id="openai_tag_suggester_taxonomy" name="openai_tag_suggester_taxonomy" class="widefat">';
                content += '<option value="post_tag" selected>Tags</option>';
                content += '<option value="hashtag">Hashtags</option>';
                content += '</select>';
                content += '</div>';
                
                // Generate button
                content += '<button type="button" id="openai_tag_suggester_button" name="openai_tag_suggester_button" ';
                content += 'class="button button-primary" style="width: 100%; margin-bottom: 10px;">';
                content += 'Generate Tag Suggestions</button>';
                
                // Results area
                content += '<div id="openai_tag_suggester_results" class="tag-results-container"></div>';
                
                content += '</div>';
                
                // Add the content to the meta box
                $inside.html(content);
                
                // Bind click event for generating tags
                $('#openai_tag_suggester_button').on('click', generateTags);
            } else {
                console.error('OpenAI Tag Suggester: Meta box inside div not found');
            }
        } else {
            console.log('OpenAI Tag Suggester: Button found, binding click event');
            // Bind click event for generating tags
            $('#openai_tag_suggester_button').on('click', generateTags);
        }
        
        // Add Select All/None functionality
        $(document).on('click', '.select-all-tags', function() {
            $('.tag-checkbox:not(:disabled)').prop('checked', true);
        });
        
        $(document).on('click', '.select-none-tags', function() {
            $('.tag-checkbox:not(:disabled)').prop('checked', false);
        });
    }
    
    function generateTags() {
        const $button = $('#openai_tag_suggester_button');
        const $results = $('#openai_tag_suggester_results');
        const selectedTaxonomy = $('#openai_tag_suggester_taxonomy').val();
        
        // Get post content based on editor type
        let postContent = '';
        
        if (openaiTagSuggester.is_classic_editor) {
            // For Classic Editor, get content from the textarea
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                // Visual tab is active
                postContent = tinyMCE.get('content').getContent();
            } else {
                // Text tab is active
                postContent = $('#content').val();
            }
        } else {
            // For Block Editor (Gutenberg), get content from the store
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                postContent = wp.data.select('core/editor').getEditedPostContent();
            }
        }
        
        // Show loading state
        $button.prop('disabled', true).text('Generating...');
        $results.html('<div class="notice notice-info"><p>Generating suggestions...</p></div>');
        
        $.ajax({
            url: openaiTagSuggester.ajax_url,
            type: 'POST',
            data: {
                action: 'openai_tag_suggester_generate_tags',
                post_id: openaiTagSuggester.post_id,
                taxonomy: selectedTaxonomy,
                content: postContent, // Send the content directly
                nonce: openaiTagSuggester.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                
                // Reset button state
                $button.prop('disabled', false).text('Generate Tag Suggestions');
                
                if (response.success) {
                    const suggestedTags = response.data.suggested;
                    const existingTags = response.data.existing;
                    
                    let tagHtml = generateTagCheckboxes(suggestedTags, existingTags);
                    
                    $results.html(tagHtml);
                } else {
                    $results.html('<div class="notice notice-error"><p>Error: ' + 
                                (response.data || 'Failed to generate tags') + '</p></div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                // Reset button state
                $button.prop('disabled', false).text('Generate Tag Suggestions');
                
                console.error('AJAX error:', {
                    status: textStatus,
                    error: errorThrown,
                    response: jqXHR.responseText
                });
                
                $results.html('<div class="notice notice-error"><p>Error: Failed to generate tags. ' + 
                            'Please try again.</p></div>');
            }
        });
    }

    function generateTagCheckboxes(suggestedTags, existingTags) {
        let tagHtml = '<div class="suggested-tags">';
        
        // Add top controls with Select All/None and Add Selected Tags
        tagHtml += '<div class="tag-controls" style="margin-bottom: 10px;">';
        tagHtml += '<button type="button" id="select_all_tags" name="select_all_tags" class="button select-all-tags">Select All</button> ';
        tagHtml += '<button type="button" id="select_none_tags" name="select_none_tags" class="button select-none-tags">Select None</button> ';
        tagHtml += '</div>';
        
        // Add a separate Add Selected Tags button with proper styling
        tagHtml += '<button type="button" class="button button-primary add-selected-tags" style="width: 100%; margin-bottom: 10px;">Add Selected Tags</button>';
        
        // Add tag checkboxes
        tagHtml += '<div class="tag-list" style="margin-bottom: 10px;">';
        suggestedTags.forEach((tag, index) => {
            const isExisting = existingTags.includes(tag.replace(/^#/, ''));
            const checkboxId = `tag_checkbox_${index}`;
            tagHtml += `
                <div class="tag-suggestion ${isExisting ? 'existing-tag' : ''}" style="margin: 5px 0;">
                    <label for="${checkboxId}">
                        <input type="checkbox" 
                               id="${checkboxId}"
                               name="suggested_tags[]"
                               class="tag-checkbox" 
                               value="${tag}"
                               ${isExisting ? 'checked disabled' : ''}>
                        ${tag} ${isExisting ? '<span class="existing-tag-label">(existing)</span>' : ''}
                    </label>
                </div>
            `;
        });
        tagHtml += '</div>';
        
        // Add bottom Add Selected Tags button
        tagHtml += '<button type="button" class="button button-primary add-selected-tags">Add Selected Tags</button>';
        tagHtml += '</div>';
        
        return tagHtml;
    }

    // Add Selected Tags handler
    $(document).on('click', '.add-selected-tags', function() {
        const $button = $(this);
        const $results = $('#openai_tag_suggester_results');
        const selectedTags = [];
        const selectedTaxonomy = $('#openai_tag_suggester_taxonomy').val();
        
        // Show loading state
        $button.prop('disabled', true).text('Adding tags...');
        
        // Get all checked tags
        $('.tag-checkbox:checked:not(:disabled)').each(function() {
            selectedTags.push($(this).val().replace(/^#/, '')); // Remove # prefix if present
        });
        
        if (selectedTags.length === 0) {
            alert('Please select at least one tag to add.');
            $button.prop('disabled', false).text('Add Selected Tags');
            return;
        }
        
        console.log('Adding tags:', {
            taxonomy: selectedTaxonomy,
            tags: selectedTags
        });
        
        // Send AJAX request to save tags
        $.ajax({
            url: openaiTagSuggester.ajax_url,
            type: 'POST',
            data: {
                action: 'openai_tag_suggester_save_tags',
                post_id: openaiTagSuggester.post_id,
                taxonomy: selectedTaxonomy,
                tags: selectedTags,
                nonce: openaiTagSuggester.nonce
            },
            success: function(response) {
                console.log('Save tags response:', response);
                
                if (response.success) {
                    // Show success message
                    $results.prepend(
                        '<div class="notice notice-success"><p>Tags added successfully!</p></div>'
                    );
                    
                    // Update the tags in the UI based on editor type
                    updateTagsInEditor(selectedTaxonomy, selectedTags);
                    
                    // Reset button
                    $button.prop('disabled', false).text('Add Selected Tags');
                } else {
                    $button.prop('disabled', false).text('Add Selected Tags');
                    $results.prepend(
                        '<div class="notice notice-error"><p>Error: ' + 
                        (response.data || 'Failed to save tags') + '</p></div>'
                    );
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Save tags error:', {
                    status: textStatus,
                    error: errorThrown,
                    response: jqXHR.responseText
                });
                
                $button.prop('disabled', false).text('Add Selected Tags');
                $results.prepend(
                    '<div class="notice notice-error"><p>Error saving tags. Please try again.</p></div>'
                );
            }
        });
    });
    
    // Update tags in the editor UI without page refresh
    function updateTagsInEditor(taxonomy, newTags) {
        if (taxonomy === 'post_tag') {
            if (openaiTagSuggester.is_classic_editor) {
                // For Classic Editor
                const $tagInput = $('#new-tag-post_tag');
                if ($tagInput.length) {
                    // Add tags to the input
                    let currentTags = $tagInput.val();
                    let tagsToAdd = newTags.join(', ');
                    
                    if (currentTags) {
                        $tagInput.val(currentTags + ', ' + tagsToAdd);
                    } else {
                        $tagInput.val(tagsToAdd);
                    }
                    
                    // Trigger the Add button
                    $('.tagadd').click();
                }
            } else {
                // For Block Editor (Gutenberg)
                if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                    // Get current tags
                    const currentTags = wp.data.select('core/editor').getEditedPostAttribute('tags') || [];
                    
                    // Create a set of new tag IDs to add
                    // This is more complex as we need to create terms if they don't exist
                    // For simplicity, we'll just refresh the page
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                }
            }
        } else {
            // For custom taxonomies, just refresh the page
            setTimeout(function() {
                window.location.reload();
            }, 1500);
        }
    }
    
    // Initialize
    initTagSuggester();
    
    // Listen for the custom initialization event
    $(document).on('openai_tag_suggester_init', function() {
        console.log('OpenAI Tag Suggester: Received init event');
        initTagSuggester();
    });
});