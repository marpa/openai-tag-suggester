jQuery(document).ready(function($) {
    // Function to generate tags
    function generateTags() {
        const $button = $('#openai_tag_suggester_button');
        const $results = $('#openai_tag_suggester_results');
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: openaiTagSuggester.ajax_url,
            type: 'POST',
            data: {
                action: 'openai_tag_suggester_generate_tags',
                post_id: openaiTagSuggester.post_id,
                nonce: openaiTagSuggester.nonce
            },
            success: function(response) {
                if (response.success) {
                    const tags = response.data;
                    // Add select all/none controls
                    const controlsHtml = `
                        <div class="tag-controls">
                            <button type="button" class="button button-secondary select-all-tags">Select All</button>
                            <button type="button" class="button button-secondary select-none-tags">Select None</button>
                        </div>
                    `;
                    
                    const tagHtml = tags.map(tag => `
                        <div class="tag-suggestion">
                            <label>
                                <input type="checkbox" class="tag-checkbox" value="${tag}"> ${tag}
                            </label>
                        </div>
                    `).join('');
                    
                    $results.html(`
                        <div class="suggested-tags">
                            ${controlsHtml}
                            <div class="tag-list">
                                ${tagHtml}
                            </div>
                            <button id="add_selected_tags" class="button button-secondary">Add Selected Tags</button>
                        </div>
                    `);

                    // Handle select all
                    $('.select-all-tags').on('click', function() {
                        $('.tag-checkbox').prop('checked', true);
                    });

                    // Handle select none
                    $('.select-none-tags').on('click', function() {
                        $('.tag-checkbox').prop('checked', false);
                    });

                    // Handle adding selected tags
                    $('#add_selected_tags').on('click', function() {
                        const selectedTags = [];
                        $('.tag-checkbox:checked').each(function() {
                            selectedTags.push($(this).val());
                        });
                        
                        if (selectedTags.length > 0) {
                            // Save tags via AJAX
                            $.ajax({
                                url: openaiTagSuggester.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'openai_tag_suggester_save_tags',
                                    post_id: openaiTagSuggester.post_id,
                                    tags: selectedTags,
                                    nonce: openaiTagSuggester.nonce
                                },
                                success: function(response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        $results.append('<div class="notice notice-error">Error saving tags</div>');
                                    }
                                }
                            });
                        }
                    });
                } else {
                    $results.html('Error: ' + response.data);
                }
            },
            error: function() {
                $results.html('Error: Failed to generate tags');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    }

    // Generate tags on page load if autoload is enabled
    if (openaiTagSuggester.autoload) {
        generateTags();
    }

    // Generate tags when button is clicked
    $('#openai_tag_suggester_button').on('click', function(e) {
        e.preventDefault();
        generateTags();
    });
});