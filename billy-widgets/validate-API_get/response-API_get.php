<?php
function widget_response($data, $run_id, $assistant_id, $thread_id, $tool_call_id){
    $authorized = $data['authorize'];
    if ($authorized === true or $authorized === "true"){
        error_log("requesting API access");
        // to do grant API access
        $tool_message = "User ganted API access";
        $user_id = get_current_user_id();
        $username = get_user_meta($user_id, 'username', true);
        error_log("username in response-API_get: " . $username);
        $encrypted_password = get_user_meta($user_id, 'encrypted_password', true);
        if (!$username || !$encrypted_password) {
            wp_send_json_error(array('message' => "Please set a username and password in the [settings page](/wp-admin/admin.php?page=bifm-plugin#settings)."));
        }
    } else {
        error_log("Rejected granting API access");
        $tool_message = "User rejectes your request for API access.";
        $user_id = NULL;
        $username = NULL;
        $encrypted_password = NULL;
    }

    global $API_URL;
    $url = $API_URL . '/assistant_chat';
    $website = home_url();  // Current website URL
    $site_info = array(
        'website' => $website,
        'username' => $username,
        'password' => $encrypted_password,
    );

    $response_tool = wp_remote_post($url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode(array(
            'message' => null,
            'tool_outputs' => array(
                'tool_call_id' => $tool_call_id,
                'output' => $tool_message,
                'status' => 'execute_tool',
                'function' => array(
                    'name' => 'API_get',
                    'arguments' => array(),
                    'id' => $data['id'],
                    'endpoint' => $data['endpoint'],
                    'how_many' => $data['how_many'],
                    'query_params' => $data['query_params'],
                    
                )
            ),
            'thread_id' => $thread_id,
            'assistant_id' => $assistant_id,
            'run_id' => $run_id,
            'site_info' => $site_info
        )),
        'method' => 'POST',
        'data_format' => 'body',
        'timeout' => 60 // Set the timeout (in seconds)
    ));
    return $response_tool;
}


