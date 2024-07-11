<?php
require ( __DIR__ . '/../bifm-config.php' );// define base url for the API

$custom_log_file = WP_CONTENT_DIR . '/custom.log';
//session info
if (!session_id()) {
    session_start();
}


//assistant start
add_action('wp_ajax_send_chat_message', 'handle_chat_message');
function handle_chat_message() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'billy-nonce')) {
        wp_send_json_error(array('message' => "Couldn't verify user"), 500);
    }
    error_log("back end handle message called");
    $message = $_POST['message'];
    $tool_call_id = $_POST['tool_call_id'];
    $widget_name = $_POST['widget_name'];
    $run_id = $_POST['run_id'];
    // Send the message to AI API
    //commenting out to build out widget!!!
    $response = callAPI($message, $widget_name, $run_id, $tool_call_id);
}

function callAPI($message, $widget_name, $run_id, $tool_call_id) {
    $assistant_id = get_option('assistant_id');
    error_log("Assistant ID: " . $assistant_id);
    if ($assistant_id === false) {
        update_option('assistant_instructions', '');
        update_option('uploaded_file_names', array());
        update_option('assistant_id', NULL);
        update_option('vector_store_id', NULL);
        //wp_send_json_error(array('message' => "Your admin hasn't configured the smart chat in the BIFM plugin."), 500);
        //wp_die();
    }
    if (isset($_SESSION['thread_id'])) {
        $thread_id = $_SESSION['thread_id'];
    } else {
        $thread_id = null;
    }

    global $API_URL;
    $url = $API_URL . '/assistant_chat';

    if ($widget_name == null) {
        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'message' => $message,
                'thread_id' => $thread_id,
                'assistant_id' => $assistant_id
            )),
            'method' => 'POST',
            'data_format' => 'body',
            'timeout' => 60 // Set the timeout (in seconds)
        ));

    } else {
        $response = widget_submission($message, $run_id, $widget_name, $assistant_id, $thread_id, $tool_call_id);
    }
    if (is_wp_error($response)) {
        $error_response = $response->get_error_message() ? $response->get_error_message() : "Unknown error when calling chat API";
        error_log($error_response);
        wp_send_json_error(array('message' => "Something went wrong: $error_response"), 500);
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        error_log("Status code from chat response: " . $status_code);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status_code == 200) {
            // check if site_info is set, if so save assistant_id to options
            if ($assistant_id == NULL && isset($response_body['site_info'])) {
                $site_info = $response_body['site_info'];
                if (isset($site_info['assistant_id'])) {
                    error_log("Assistant ID stored from first-time call to chat: " . $site_info['assistant_id']);
                    update_option('assistant_id', $site_info['assistant_id']);
                }
            }
            handle_response($response_body, $message);
        } else {
            error_log("Got a not 200 response" . $status_code);
            if (isset($response_body['message'])){
                $error_response = $response_body['message'];
            } elseif (isset($response_body['error'])){
                $error_response =  $response_body['error'];
            } else{
                $error_response = "API for chat returned an error with code: " .  $status_code;
            }        
            error_log("Error_message: ");
            error_log($error_response);
            wp_send_json_error(array('message' => $error_response), $status_code > 0 ? $status_code : 500);
        }
    }
    wp_die();
}

// Handle response from API
function handle_response($body, $message) {
    // Case where just a regular chat response
    if (isset($body['thread_id'])) {
        $_SESSION['thread_id'] = $body['thread_id'];
        $thread_id = $body['thread_id'];
        // Get the existing thread data from the WP option
        $thread_data = get_option('assistant_thread_data', array());

        // Check if the thread ID is not already in the list of threads
        if (!array_key_exists($thread_id, $thread_data)) {
            // Create a snippet of the first 20 characters of the message
            $message_snippet = substr($message, 0, 20) . '...';
            // Add the new thread ID with its message snippet
            $thread_data[$thread_id] = $message_snippet;
        }

        // Make sure only the last 5 threads are kept
        if (count($thread_data) > 5) {
            // Removes the oldest thread ID
            array_shift($thread_data); 
        }

        // Update the option with the new list of thread data
        update_option('assistant_thread_data', $thread_data);
    }

    if (!isset($body['status']) || $body['status'] == 'chatting' || $body['status'] == 'error') {
        if (isset($body['data'])) {
            wp_send_json_success(array('message' => $body['data']));
        } else {
            wp_send_json_success(array('message' => $body['message']));
        }
    //
    } else {
        if ($body['status'] == 'reply_with_widget' || $body['status'] == 'needs_authorization') {
            $tool_name = $body['tool_name'];
            // eventually get rid of this filter, might want a list of approved tools to check against
            #try - catch block
            try {
                $parameters = $body['data']['parameters'];
                $tool_call_id = $body['tool_call_id'];
                $run_id = $body['run_id'];
                // to do, estamos pasando empty run id y tool call id 
                $response = include_widget($tool_name, $parameters, $run_id, $tool_call_id);
                if (isset($response['thread_id'])) {
                    $_SESSION['thread_id'] = $response['thread_id'];
                }
                wp_send_json_success(array('tool' => true, 'message' => "", 'widget_object' => $response));
            } catch (Exception $e) {
                wp_send_json_success(array('message' => 'There was an error in getting ' . $tool_name . '.'));
            }
        }
    }
}

// add action for case where new_chat is clicked
add_action('wp_ajax_new_chat', 'new_chat');
function new_chat() {
    //check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'billy-nonce')) {
        wp_send_json_error(array('message' => "Couldn't verify user"), 500);
    }
    $assistant_id = get_option('assistant_id');
    if ($assistant_id === false) {
        wp_send_json_error(array('message' => "Your admin hasn't configured the smart chat in the BIFM plugin."), 500);
        wp_die();
    }
    // clear the thread_id from session
    unset($_SESSION['thread_id']);
    $thread_id = null;
    //return success
    wp_send_json_success(array('message' => 'New chat started', 'thread_id' => $thread_id));
}

/* Load an old thread */
add_action('wp_ajax_load_billy_chat', 'load_billy_chat'); // wp_ajax_{action} for logged-in users
function load_billy_chat() {
    error_log("load chat called");
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'billy-nonce')) {
        wp_send_json_error(array('message' => "Couldn't verify user"), 500);
    }
    if (!session_id()) {
        session_start();
    }
    // if no thread_id is passed, load current thread
    if (!isset($_POST['thread_id']) && isset($_SESSION['thread_id'])) {
            $thread_id = $_SESSION['thread_id'];
    } else {
        $thread_id = sanitize_text_field($_POST['thread_id']);
        $_SESSION['thread_id'] = $thread_id; // Set the new current thread ID
    }

    $thread_ids = get_option('assistant_thread_data');
    if (($key = array_search($thread_id, $thread_ids)) !== false) {
        unset($thread_ids[$key]);
        array_unshift($thread_ids, $thread_id); // Move this thread to the top
        update_option('assistant_thread_data', $thread_ids);
    }

    // Call the API to get the thread
    global $API_URL;
    $url = $API_URL . '/load_thread'; // Make sure this matches your Flask route
    error_log("calling api for load threads");
    // post to the API
    $response = wp_remote_post($url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode(array(
            'thread_id' => $thread_id
        )),
        'data_format' => 'body',
        'timeout' => 60 // Set the timeout (in seconds)
    ));
    
    if ($response instanceof WP_Error) {
        error_log("Response to load thread contains an error.");
        wp_send_json_error(array('message' => "Couldn't load thread: " . $response->get_error_message()), 500);
    } elseif (wp_remote_retrieve_response_code($response) != 200) {
        error_log("Response to load thread is not 200.");
        wp_send_json_error(array('message' => "Couldn't load thread: " . wp_remote_retrieve_response_message($response)), 500);
    } else {
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        error_log("Response body status: ", $response_body['status']);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        wp_send_json_success($data);
    }
    wp_send_json_success(array('message' => "Thread $thread_id loaded and moved up."),200);
}




// handle widgets //
function include_widget($widget_name, $parameters, $run_id, $tool_call_id) {
    include_once __DIR__ . '/../billy-widgets/validate-' . $widget_name . '/validate-' . $widget_name . '.php';
    $response = get_widget($parameters, $run_id, $tool_call_id);
    return $response;
}

//widget submission
function widget_submission($message, $run_id, $widget_name, $assistant_id, $thread_id, $tool_call_id) {
    include_once __DIR__ . '/../billy-widgets/validate-' . $widget_name . '/response-' . $widget_name . '.php';
    $response = widget_response($message, $run_id, $assistant_id, $thread_id, $tool_call_id);
    return $response;
}