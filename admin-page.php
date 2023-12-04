<?php
    $nonce = wp_create_nonce('my_custom_action');

    // Materialize CSS and Icons
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">';
    echo '<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">';

    // Tabs structure
    echo '<div class="row">';
    echo '<h3>API URL return to original</h3>';
    echo '<h3>Adjust bulk blog creator too</h3>';
    echo '<div class="col s12">';
    echo '<ul class="tabs">';
    echo '<li class="tab col s4"><a href="#widget-generator">Widget Generator</a></li>';
    echo '<li class="tab col s4"><a href="#blog-generator">Blog Generator</a></li>';
    echo '<li class="tab col s4"><a href="#settings">Settings</a></li>';
    echo '</ul>';
    echo '</div>';

    // Widget Generator Tab
    echo '<div id="widget-generator" class="col s12">';
    echo '<h5>Widget Generator</h5>';
        // Button to create a new widget
        echo '<button id="createNewWidget" class="btn waves-effect waves-light red lighten-2">Create new widget</button>';
        echo '<button id="backButton" class="btn waves-effect waves-light red lighten-2" style="display: none;"><i class="material-icons left">arrow_back</i>Back</button>';
    
        $widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
        $widget_types = $widgets_manager->get_widget_types();
    
        echo '<ul class="collection with-header" id="widget-list" style="max-width: 500px;">';
        echo '<li class="collection-header" style="margin-bottom: 0;"><h4>Your widgets</h4></li>';
        foreach ($widget_types as $widget) {
            $reflector = new ReflectionClass(get_class($widget));
            $widget_file_path = $reflector->getFileName();
            $widget_name = $widget->get_name(); // Get the widget name
        
            // Check if the widget's file is in your custom folder
            if (strpos($widget_file_path, 'bifm-widgets') !== false) {
                echo '<li class="collection-item" id="' . esc_attr($widget_name) . '">';
                echo '<div>' . esc_html($widget->get_title()) . '<a href="#!" class="secondary-content delete-widget" data-widget-name="' . esc_attr($widget_name) . '" style="margin-left: 5px;"><i class="material-icons">delete</i></a>';
                echo '</div></li>';
            }
        }
    echo '</div>';

    // Blog Generator Tab
    echo '<div id="blog-generator" class="col s12">';
    echo '<h5>Blog Generator</h5>';
    // Button to create a new blog
    echo '<button id="createNewBlog" class="btn waves-effect waves-light red lighten-2">Create new blog post</button>';
    echo '<button id="backButton" class="btn waves-effect waves-light red lighten-2" style="display: none;"><i class="material-icons left">arrow_back</i>Back</button>';
    echo '</div>';

    // Settings Tab
    echo '<div id="settings" class="col s12">';
    echo '<h5>Settings</h5>';
    // Add settings content here...
        echo '<p>Here you can update the settings for blog creation.</p>';
        echo '<p>Please note that the blog creator requires the "JSON Basic Authentication" plugin by the Wordpress API team to be installed.</p>';

        // Start of the form
        echo '<form id="bifm-settings-form" action="#" method="post">';

        // Blog author's username
        echo '<div class="row"><div class="input-field col s12 l4">';
        echo '<input id="blog_author_username" type="text" name="blog_author_username" class="validate">';
        echo '<label for="blog_author_username">Blog author\'s username</label>';
        echo '<p style="color:red">Create an author account that only has editor access, DO NOT use an admin account. </p>';
        echo '</div></div>';

        // Blog author's password
        echo '<div class="row"><div class="input-field col s12 l4">';
        echo '<input id="blog_author_password" type="password" name="blog_author_password" class="validate">';
        echo '<label for="blog_author_password">Blog author\'s password</label>';
        echo '</div></div>';

        // Language for new blog posts
        echo '<div class="row"><div class="input-field col s12 l4">';
        echo '<select id="blog_language" name="blog_language[]" >';
        echo '<option value="" disabled selected>Choose language</option>';
        echo '<option value="English">English</option>';
        echo '<option value="Spanish">Spanish</option>';
        echo '</select>';
        echo '<label for="blog_language">Language for new blog posts</label>';
        echo '</div></div>';

        // Website description
        echo '<div class="row"><div class="input-field col s12 l8">';
        echo '<textarea id="website_description" name="website_description" class="materialize-textarea"></textarea>';
        echo '<label for="website_description">Describe your website / company in a couple of sentences</label>';
        echo '</div></div>';

        // Submit button
        echo '<button class="btn waves-effect waves-light" type="submit" name="action">Update</button>';

        echo '</form>';
    echo '</div>'; // Close the settings tab

    echo '<div id="warningMessage" class="card-panel yellow darken-2" style="display: none;"></div>';

    // Inline JavaScript for Tab and multiple choice Functionality
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var elems = document.querySelectorAll(".tabs");
            var instances = M.Tabs.init(elems, {});
            var elems2 = document.querySelectorAll("select");
            var instances2 = M.FormSelect.init(elems2, {});
        });
    </script>';


    echo '<script>var my_script_object = { ajax_url: "' . esc_js(admin_url('admin-ajax.php')) . '", nonce: "' . esc_js($nonce) . '" };</script>';


    // Materialize JavaScript
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>';
    ?>
    




