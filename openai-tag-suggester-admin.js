jQuery(document).ready(function($) {
    function generateTags() {
        const $button = $('#openai_tag_suggester_button');
        const $results = $('#openai_tag_suggester_results');
        const selectedTaxonomy = $('#openai_tag_suggester_taxonomy').val();
        
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
        tagHtml += '<button type="button" class="button button-primary add-selected-tags">Add Selected Tags</button>';
        tagHtml += '</div>';
        
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

    // Bind click event
    $(document).on('click', '#openai_tag_suggester_button', generateTags);

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
                        '<div class="notice notice-success"><p>Tags added successfully! Refreshing page...</p></div>'
                    );
                    
                    // Refresh the page after a short delay
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
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

    // Bind click event
    $(document).on('click', '#openai_tag_suggester_button', generateTags);
});