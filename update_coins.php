<?php
/*
* Plugin Name: Update Coins Plugin
* Description: Custom API to Update Coins for WooCommerce Rewards and Points Plugin.
* Version: 1.0
* Author: Tanmay Patil
*/


add_action('rest_api_init', 'register_custom_points_route');

function register_custom_points_route() {
    register_rest_route(
        'wc/v3',
        '/points-and-rewards',
        array(
            'methods' => array('GET', 'POST'), // Add POST method
            'callback' => 'handle_points_request',
            'args' => array(
                'user_email' => array(
                    'description' => 'User Email for whom to retrieve points.',
                    'type' => 'string',
                    'required' => false,
                ),
                'user_id' => array(
                    'description' => 'User ID for whom to retrieve points.',
                    'type' => 'integer',
                    'required' => false,
                ),
            ),
        )
    );
}


// Getting user_id from user_email function.
function get_user_id_by_email($user_email) {
	//echo $user_email;
    $user = get_user_by('email', $user_email);
    if ($user) {
		//echo $user->ID;
        return $user->ID;
		
    } else {
        return 0; // Return 0 if the user is not found
    }
}

// New callback function for handling POST requests
function handle_points_request($request) {
    if ($request->get_method() === 'POST') {
        return handle_points_post_request($request);
    }
	
    $user_id = $request->get_param('user_id');
	$user_email = $request->get_param('user_email');
	
	//var_dump($user_id);
	
	if (empty($user_id) && !empty($user_email)) {
        $user_id = get_user_id_by_email($user_email);
    }

    if (empty($user_id)) {
        return new WP_REST_Response(array('error' => 'Missing or invalid user_id parameter'), 400);
    }
	
	// If else condition to get the data by using user_id or user_email.
    if ($user_id) {
        $user_data = get_user_data_by_id($user_id);
    } elseif ($user_email) {
        $user_data = get_user_data_by_email($user_email);
    } else {
        return new WP_REST_Response(array('error' => 'Missing user_id or user_email parameter'), 400);
    }

    $response_data = array(
        'message' => 'GET request processed successfully',
        'user_id' => $user_id,
        'user_email' => $user_data->user_email,
        'points' => $user_data->points,
        'points_balance' => $user_data->points_balance,
        'order_id' => $user_data->order_id,
    );

    return new WP_REST_Response($response_data, 200);
}

// New function to handle POST requests
function handle_points_post_request($request) {
    // Handle your POST logic here
    //var_dump($user_id);
    $data = $request->get_json_params(); // Assuming data is sent in JSON format
	
	$user_email = $data['user_email'];
    $points = $data['points'];
    $points_balance = $data['points_balance'];
	
	// Get user ID based on the user_email
    $user_id = get_user_id_by_email($user_email);
	
	// Check if the user_id is valid
    if (!$user_id) {
        return new WP_REST_Response(array('error' => 'User not found'), 404);
    }
	
	// Get user's current points and points balance
    $total_data = get_user_points_and_balance($user_id);

    // Calculate cumulative points and points balance
    $cumulative_points = $total_data->points + $points;
    $cumulative_points_balance = $total_data->points_balance + $points_balance;

    // Update points and points_balance in the database
    update_user_points($user_id, $points, $points_balance);

    $response_data = array(
        'message' => 'POST request processed successfully',
        'user_id' => $user_id,
        'user_email' => $user_email,
        'points' => $cumulative_points,
        'points_balance' => $cumulative_points_balance,
    );

    return new WP_REST_Response($response_data, 200);
}

//Updating the Coins in the database
function update_user_points($user_id, $points, $points_balance) {
    global $wpdb;

    $points_table = $wpdb->prefix . 'wc_points_rewards_user_points';

        // If the user doesn't have a record, insert a new one
        $wpdb->insert(
            $points_table,
            array('user_id' => $user_id, 'points' => $points, 'points_balance' => $points_balance)
        );
}


//Getting User data by User ID
function get_user_data_by_id($user_id) {
    global $wpdb;

    $user_table = $wpdb->prefix . 'users';
    $points_table = $wpdb->prefix . 'wc_points_rewards_user_points';

    $query = $wpdb->prepare(
        "SELECT u.user_email, p.points_balance, p.points, p.order_id
        FROM $user_table AS u
        LEFT JOIN $points_table AS p ON u.ID = p.user_id
        WHERE u.ID = %d",
        $user_id
    );

    return $wpdb->get_row($query);
}

//Getting User data by Email ID
function get_user_data_by_email($user_email) {
    global $wpdb;

    $user_table = $wpdb->prefix . 'users';
    $points_table = $wpdb->prefix . 'wc_points_rewards_user_points';

    $query = $wpdb->prepare(
        "SELECT u.user_email, p.points_balance, p.points, p.order_id
        FROM $user_table AS u
        LEFT JOIN $points_table AS p ON u.ID = p.user_id
        WHERE u.user_email = %s",
        $user_email
    );

    return $wpdb->get_row($query);
}

function get_user_points_and_balance($user_id) {
    global $wpdb;

    $points_table = $wpdb->prefix . 'wc_points_rewards_user_points';
	
    // Prepare and execute the SQL query
    $query = $wpdb->prepare(
        "SELECT SUM(points) as points, SUM(points_balance) as points_balance
        FROM $points_table
        WHERE user_id = %d",
        $user_id
    );

    $user_data = $wpdb->get_row($query);

    // If the user doesn't have a record, return default values
    if (!$user_data) {
        return (object) array(
            'points' => 0,
            'points_balance' => 0,
        );
    }

    return $user_data;
}