// Fetch the categories when the page loads
(function($) {
    $(document).ready(function() {
        // Fetch the categories
        $.ajax({
            url: cbc_object.ajax_url,
            type: 'post',
            data: {
                action: 'cbc_get_categories',
                nonce: cbc_object.single_post_nonce
            },
            success: function(response) {
                if (response.success) {
                    let categories = response.data;
                    let categoryInput = $('#category_input');
                    categories.forEach(category => {
                        categoryInput.append($('<option>').val(category.id).text(category.name));
                    });
                    categoryInput.append('<option value="other">Other...</option>');
                    categoryInput.formSelect(); // Re-initialize the Materialize dropdown
                }
            }
        });

        // Create a new category if the user selects "Other..." from the dropdown
        $('#category_input').change(function() {
            let selected = $(this).val();
            if (selected === 'other') {
                let newCategory = $("<div>").text(prompt("Please enter the new category:")).text();
                if (newCategory) {
                    // Send AJAX request to create the new category
                    $.ajax({
                        url: cbc_object.ajax_url,
                        type: 'post',
                        data: {
                            action: 'cbc_create_category',
                            nonce: cbc_object.single_post_nonce,
                            category_name: newCategory
                        },
                        success: function(response) {
                            if (response.success) {
                                // Add the new category to the dropdown with the returned ID as value
                                let newCategoryId = response.data.id;
                                $('#category_input').append('<option value="' + newCategoryId + '" selected>' + newCategory + '</option>');
                                $('#category_input').formSelect();
                            } else {
                                alert('Failed to create new category. Please try again.');
                            }
                        }
                    });
                } else {
                    $(this).val(""); // Clear selection
                    $(this).formSelect();
                }
            }
        });
    });
})(jQuery);

(function($) {
    $(document).ready(function() {
        let itemCount = 1;

        // Add more items to be generated on "add more +"
        $('.add-more').on('click', function(e) {
            e.preventDefault();
            addNewInput();
        });

        // Function to add new input
        function addNewInput() {
            itemCount++;
            let newInput = `
                <div class="input-field col s12 writer-input">
                    <input id="description${itemCount}" type="text" class="validate">
                    <label for="description${itemCount}">Keyphrase (online search term) you'd like to capture</label>
                    <select id="category_input${itemCount}" class="browser-default category-dropdown">
                        <option value="" disabled selected>Category</option>
                    </select>
                </div>`;
            $('#writer-buttons').before(newInput);

            // Fetch categories for new input
            $.ajax({
                url: cbc_object.ajax_url,
                type: 'post',
                data: {
                    action: 'cbc_get_categories',
                    nonce: cbc_object.single_post_nonce
                },
                success: function(response) {
                    if (response.success) {
                        let categories = response.data;
                        let categoryInput = $(`#category_input${itemCount}`);
                        categories.forEach(category => {
                            categoryInput.append($('<option>').val(category.id).text(category.name));
                        });
                        categoryInput.append('<option value="other">Other...</option>');
                        categoryInput.formSelect(); // Re-initialize the Materialize dropdown
                    }
                }
            });
        }

        // Handle create post submissions
        $('#generate-blogposts-button').on('click', function(e) {
            e.preventDefault();
            let items = [];
            $('.writer-input').each(function() {
                let keyphrase = $(this).find('input[type="text"]').val();
                let categoryValue = $(this).find('select').val();
                let categoryName = $(this).find('select option:selected').text();
                if (keyphrase) {
                    items.push({ keyphrase, category: categoryValue, category_name: categoryName });
                }
            });

            if (items.length === 0) {
                showMessage('Please add at least one item with a keyphrase and category.');
                return;
            }

            if (items.length === 1) {
                submitSingleItem(items[0]);
            } else {
                console.log("Submitting bulk items");
                console.log(items.length);
                submitBulkItems(items);
            }
        });

        function submitSingleItem(item) {
            let data = {
                action: 'cbc_create_blog',
                nonce: cbc_object.single_post_nonce,
                keyphrase: item.keyphrase,
                category: item.category,
                category_name: item.category_name
            };

            $.post(cbc_object.ajax_url, data, function(response) {
                handleResponse(response);
            }).fail(handleError);
        }

        function submitBulkItems(items) {
            let data = {
                action: 'cbc_create_bulk_blogs',
                nonce: cbc_object.bulk_upload_nonce,
                items: items
            };

            $.post(cbc_object.ajax_url, data, function(response) {
                handleResponse(response);
            }).fail(handleError);
        }

        function handleResponse(response) {
            if (response.status === 202) {
                //json load the response message
                response_data = JSON.parse(response.data);
                if (response_data.message) {
                    showMessage(response_data.message);
                } else {
                    showMessage('The blog is being created. This might take up to 2 minutes...');
                }
            } else {
                let message =  'An unknown error occurred.';
                if (response.data && response.data.message) {
                    message = response.data.message;
                } else if (response.message) {
                    message = response.message;
                }
                showMessage(message)
            }
        }

        function handleError(jqXHR, textStatus, errorThrown) {
            let parsedData = (jqXHR.responseJSON && jqXHR.responseJSON.data) ? JSON.parse(jqXHR.responseJSON.data) : null;
            let errorMessage = 'Failed to connect to the backend.';
            if (parsedData && parsedData.message) {
                errorMessage = parsedData.message;
            } else if (parsedData && parsedData.error) {
                errorMessage = parsedData.error;
            }
            showMessage(errorMessage);
        }

        // Process clicks on back button
        document.getElementById('backButton').addEventListener('click', goBack);
        function goBack() {
            window.location.href = 'admin.php?page=bifm-plugin';
        }

        // Delete request
        $('#posts-table-div').on('click', '.delete-post', function(e) {
            e.preventDefault();
            let postId = $(this).data('post-id');
            if (!postId) return;

            let data = {
                action: 'cbc_delete_blog',
                nonce: cbc_object.single_post_nonce,
                post_id: postId
            };

            $.post(cbc_object.ajax_url, data, function(response) {
                if (response.success) {
                    $(this).closest('tr').remove();
                } else {
                    alert('Failed to delete the post. Please try again.');
                }
            }).fail(function() {
                alert('Failed to connect to the backend.');
            });
        });

        // Handle suggestion button clicks
        $('#suggestion-buttons .suggestion-button').on('click', function(e) {
            e.preventDefault();
            let suggestionText = $(this).text();
            fillSuggestion(suggestionText);
        });

        function fillSuggestion(suggestionText) {
            let emptyInput = $('.writer-input').filter(function() {
                return !$(this).find('input[type="text"]').val();
            }).first();

            if (emptyInput.length) {
                emptyInput.find('input[type="text"]').val(suggestionText).next('label').addClass('active');
            } else {
                addNewInput();
                $(`#description${itemCount}`).val(suggestionText).next('label').addClass('active');
            }
        }
    });
})(jQuery);


function showMessage(message) {
    // scroll to top
    window.scrollTo(0, 0);
    $('#cbc_response').html(message).show();
    setTimeout(function() {
        $('#cbc_response').fadeOut('slow');
        // refresh page
        location.reload();
    }, 5000);
}
