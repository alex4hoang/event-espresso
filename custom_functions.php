<?php
/**
 * Generating random Room ID for event espresso
 * Install by adding to custom_functions.php
 */

function event_espresso_add_attendees_to_db( $event_id = NULL, $session_vars = NULL, $skip_check = FALSE ) {
		do_action('action_hook_espresso_log', __FILE__, __FUNCTION__, '');
		
		//Security check using nonce
		if ( empty($_POST['reg_form_nonce']) || !wp_verify_nonce($_POST['reg_form_nonce'],'reg_nonce') ){
			print '<h3 class="error">'.__('Sorry, there was a security error and your registration was not saved.', 'event_espresso').'</h3>';
			return;
		}

		global $wpdb, $org_options, $espresso_premium;
		
		//Defaults
		$data_source = $_POST;
		$att_data_source = $_POST;
		$multi_reg = FALSE;
		$notifications = array( 'coupons' => '', 'groupons' => '' );
		
		if ( ! is_null($event_id) && ! is_null($session_vars)) {
			//event details, ie qty, price, start..
			$data_source = $session_vars['data']; 
			//event attendee info ie name, questions....
			$att_data_source = $session_vars['event_attendees']; 
			$multi_reg = TRUE;
		} else {
			$event_id = absint( $data_source['event_id'] );
		}
		
		//Check for existing registrations
		//check if user has already hit this page before ( ie: going back n forth thru reg process )
		$prev_session_id = isset($_SESSION['espresso_session']['id']) && !empty($_SESSION['espresso_session']['id']) ? $_SESSION['espresso_session']['id'] : '';
		if ( is_null( $session_vars )) {
			$SQL = "SELECT id FROM " . EVENTS_ATTENDEE_TABLE . " WHERE attendee_session=%s";
			$prev_session_attendee_id = $wpdb->get_col( $wpdb->prepare( $SQL, $_SESSION['espresso_session']['id'] ));
			if ( ! empty( $prev_session_attendee_id )) {
				$_SESSION['espresso_session']['id'] = array();
				ee_init_session();
			}
		}
		
		
		//Check to see if the registration id already exists
		$incomplete_filter = ! $multi_reg ? " AND payment_status ='Incomplete'" : '';
		$SQL = "SELECT attendee_session, id, registration_id FROM " . EVENTS_ATTENDEE_TABLE . " WHERE attendee_session =%s AND event_id = %d";
		$SQL .= $incomplete_filter;
		$check_sql = $wpdb->get_results($wpdb->prepare( $SQL, $prev_session_id, $event_id ));
		$nmbr_of_regs = $wpdb->num_rows;
		static $loop_number = 1;
		// delete previous entries from this session in case user is jumping back n forth between pages during the reg process
		if ( $nmbr_of_regs > 0 && $loop_number == 1 ) {
			if ( !isset( $data_source['admin'] )) {
				
				$SQL = "SELECT id, registration_id FROM " . EVENTS_ATTENDEE_TABLE . ' ';
				$SQL .= "WHERE attendee_session = %s ";
				$SQL .= $incomplete_filter;
				
				if ( $mer_attendee_ids = $wpdb->get_results($wpdb->prepare( $SQL, $prev_session_id ))) {
					foreach ( $mer_attendee_ids as $v ) {
						//Added for seating chart addon
						if ( defined('ESPRESSO_SEATING_CHART')) {				
							$SQL = "DELETE FROM " . EVENTS_SEATING_CHART_EVENT_SEAT_TABLE . ' ';
							$SQL .= "WHERE attendee_id = %d";
							$wpdb->query($wpdb->prepare( $SQL, $v->id ));
						}
						//Delete the old attendee meta
						do_action('action_hook_espresso_save_attendee_meta', $v->id, 'original_attendee_details', '', TRUE);
					}			
				}

				$SQL = "DELETE t1, t2 FROM " . EVENTS_ATTENDEE_TABLE . "  t1 ";
				$SQL .= "JOIN  " . EVENTS_ANSWER_TABLE . " t2 on t1.id = t2.attendee_id ";
				$SQL .= "WHERE t1.attendee_session = %s ";
				$SQL .= $incomplete_filter;
				$wpdb->query($wpdb->prepare( $SQL, $prev_session_id ));
				
				//Added by Imon
				// First delete attempt might fail if there is no data in answer table. So, second attempt without joining answer table is taken bellow -
				$SQL = " DELETE FROM " . EVENTS_ATTENDEE_TABLE . ' ';
				$SQL .= "WHERE attendee_session = %s ";
				$SQL .= $incomplete_filter;
				$wpdb->query($wpdb->prepare( $SQL, $prev_session_id ));
	
				// Clean up any attendee information from attendee_cost table where attendee is not available in attendee table
				event_espresso_cleanup_multi_event_registration_id_group_data();		
				
			}
		}
		$loop_number++;
		
	// Generate unique Room_ID	

		$unique_ref_length = 6;
		// A true/false variable that lets us know if we've found a unique reference number or not
		$unique_ref_found = false;
		$possible_chars = "23456789BCDFGHJKMNPQRSTVWXYZ";
		
		// Until we find a unique reference, keep generating new ones
		while (!$unique_ref_found) {

			// Start with a blank reference number
			$room_id = "";	
			// Set up a counter to keep track of how many characters have been added 
			$i = 0;
	
			// Add random characters from $possible_chars to $unique_ref 
			// until $unique_ref_length is reached
			while ($i < $unique_ref_length) {
	
				// Pick a random character from the $possible_chars list
				$char = substr($possible_chars, mt_rand(0, strlen($possible_chars)-1), 1);		
				$room_id .= $char;		
				$i++;
				
			}	
			
			// $room_id = apply_filters( 'filter_hook_espresso_room_id', $room_id2 );				
			// $room_id = $room_prefix . "" . $room_id1;
			
			// Our new unique reference number is generated.
			// Lets check if it exists or not
			$roomQuery = "SELECT room_id FROM ". EVENTS_ATTENDEE_TABLE ." WHERE room_id='".$room_id."'";
			$result = $wpdb->get_results($wpdb->prepare($roomQuery, null)) or define( 'DIEONDBERROR', true);
			if ($wpdb->num_rows == 0) {
	
				// We've found a unique number. Lets set the $unique_ref_found
				// variable to true and exit the while loop
				$unique_ref_found = true;	
			}
		}

		// Generate unique Room_ID END			
		
		
		//Check if added admin
		$skip_check = $skip_check || isset( $data_source['admin'] ) ? TRUE : FALSE;
		
		//If added by admin, skip the recaptcha check
		if ( espresso_verify_recaptcha( $skip_check )) {

			array_walk_recursive($data_source, 'wp_strip_all_tags');
			array_walk_recursive($att_data_source, 'wp_strip_all_tags');

			array_walk_recursive($data_source, 'espresso_apply_htmlentities');
			array_walk_recursive($att_data_source, 'espresso_apply_htmlentities');

			// Will be used for multi events to keep track of event id change in the loop, for recording event total cost for each group
			static $temp_event_id = ''; 
			//using this var to keep track of the first attendee
			static $attendee_number = 1; 
			static $total_cost = 0;
			static $primary_att_id = NULL;	

			if ($temp_event_id == '' || $temp_event_id != $event_id) {
				$temp_event_id = $event_id;
				$event_change = 1;
			} else {
				$event_change = 0;
			}
			
			$event_cost = isset($data_source['cost']) && $data_source['cost'] != '' ? $data_source['cost'] : 0.00;
			$final_price = $event_cost;

			$fname		= isset($att_data_source['fname']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['fname']) ), ENT_QUOTES, 'UTF-8' ) : '';
			$lname		= isset($att_data_source['lname']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['lname']) ), ENT_QUOTES, 'UTF-8' ) : '';
			$address	= isset($att_data_source['address']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['address']) ), ENT_QUOTES, 'UTF-8' ) : '';
			$address2	= isset($att_data_source['address2']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['address2']) ), ENT_QUOTES, 'UTF-8' ) : '';
			$city		= isset($att_data_source['city']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['city']) ), ENT_QUOTES, 'UTF-8' ) : '';
			$state		= isset($att_data_source['state']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['state']) ), ENT_QUOTES, 'UTF-8' ) : '';
			$zip		= isset($att_data_source['zip']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['zip']) ), ENT_QUOTES, 'UTF-8' ) : '';
			$phone		= isset($att_data_source['phone']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['phone']) ), ENT_QUOTES, 'UTF-8' ) : '';
			$email		= isset($att_data_source['email']) ? html_entity_decode( trim( sanitize_text_field($att_data_source['email']) ), ENT_QUOTES, 'UTF-8' ) : '';


			$SQL = "SELECT question_groups, event_meta FROM " . EVENTS_DETAIL_TABLE . " WHERE id = %d";
			$questions = $wpdb->get_row( $wpdb->prepare( $SQL, $event_id ));
			$event_meta = maybe_unserialize( $questions->event_meta );
			$questions = maybe_unserialize( $questions->question_groups );

			// Adding attenddee specific cost to events_attendee table
			if (isset($data_source['admin'])) {
				$attendee_quantity = 1;
				$final_price	= (float)$data_source['event_cost'];
				$orig_price		= (float)$data_source['event_cost'];
				$price_type		=  __('Admin', 'event_espresso');
			} elseif (isset($data_source['seat_id'])) {
				// Added for seating chart add-on
				// If a seat was selected then price of that seating will be used instead of event price
				$final_price	= (float)seating_chart::get_purchase_price($data_source['seat_id']);
				$orig_price		= (float)$final_price;
				$price_type		= $data_source['seat_id'];
					
			} elseif ( isset( $att_data_source['price_id'] ) && ! empty( $att_data_source['price_id'] )) {
			
				if ( $att_data_source['price_id'] == 'free' ) {
					$orig_price		= 0.00;
					$final_price	= 0.00;
					$price_type		=  __('Free Event', 'event_espresso');		
				} else {
					$orig_price		= event_espresso_get_orig_price_and_surcharge( (int)$att_data_source['price_id'] );
					$final_price	= isset( $att_data_source['price_id'] ) ? event_espresso_get_final_price( absint($att_data_source['price_id']), $event_id, $orig_price ) : 0.00;
					$price_type		= isset( $att_data_source['price_id'] ) ? espresso_ticket_information( array( 'type' => 'ticket', 'price_option' => absint($att_data_source['price_id']) )) : '';
					$surcharge		= event_espresso_calculate_surcharge( (float)$orig_price->event_cost , (float)$orig_price->surcharge, $orig_price->surcharge_type );
					$orig_price		= (float)number_format( $orig_price->event_cost + $surcharge, 2, '.', '' ); 			
				}
				
			} elseif ( isset( $data_source['price_select'] ) && $data_source['price_select'] == TRUE ) {
				
				//Figure out if the person has registered using a price selection
				$price_options	= explode( '|', sanitize_text_field($data_source['price_option']), 2 );
				$price_id		= absint($price_options[0]);
				$price_type		= $price_options[1];
				$orig_price		= event_espresso_get_orig_price_and_surcharge( $price_id );
				$final_price	= event_espresso_get_final_price( $price_id, $event_id, $orig_price );
				$surcharge		= event_espresso_calculate_surcharge( $orig_price->event_cost , $orig_price->surcharge, $orig_price->surcharge_type );
				$orig_price		= (float)number_format( $orig_price->event_cost + $surcharge, 2, '.', '' ); 
				
			} else {
			
				if ( $data_source['price_id'] == 'free' ) {
					$orig_price		= 0.00;
					$final_price	= 0.00;
					$price_type		=  __('Free Event', 'event_espresso');		
				} else {
					$orig_price		= event_espresso_get_orig_price_and_surcharge( absint($data_source['price_id']) );
					$final_price	= isset( $data_source['price_id'] ) ? event_espresso_get_final_price( absint($data_source['price_id']), $event_id, $orig_price ) : 0.00;
					$price_type		= isset($data_source['price_id']) ? espresso_ticket_information(array('type' => 'ticket', 'price_option' => absint($data_source['price_id']))) : '';
					$surcharge		= event_espresso_calculate_surcharge( $orig_price->event_cost , $orig_price->surcharge, $orig_price->surcharge_type );
					$orig_price		= (float)number_format( $orig_price->event_cost + $surcharge, 2, '.', '' ); 
				}
			
			}

			$final_price		= apply_filters( 'filter_hook_espresso_attendee_cost', $final_price );
			$attendee_quantity	= isset( $data_source['num_people'] ) ? $data_source['num_people'] : 1;
			$coupon_code		= '';

			if ($multi_reg) {			
				$event_cost		= $_SESSION['espresso_session']['grand_total'];
			} 
			
			do_action('action_hook_espresso_log', __FILE__, __FUNCTION__, 'line '. __LINE__ .' : attendee_cost=' . $final_price);

			$event_cost = apply_filters( 'filter_hook_espresso_cart_grand_total', $event_cost ); 
			$amount_pd = 0.00;


			//Check if the registration id has been created previously.
			$registration_id = empty($wpdb->last_result[0]->registration_id) ? apply_filters('filter_hook_espresso_registration_id', $event_id) : $wpdb->last_result[0]->registration_id;

			$txn_type = "";

			if (isset($data_source['admin'])) {	
					
				$payment_status		= "Completed";
				$payment			= "Admin";
				$txn_type			= __('Added by Admin', 'event_espresso');
				$payment_date		= date(get_option('date_format'));
				$amount_pd			= !empty($data_source['event_cost']) ? $data_source['event_cost'] : 0.00;
				$registration_id	= uniqid('', true);
				$_SESSION['espresso_session']['id'] = uniqid('', true);
				$room_id			= uniqid();

				
			} else {

				//print_r( $event_meta);
				$default_payment_status = $event_meta['default_payment_status'] != '' ? $event_meta['default_payment_status'] : $org_options['default_payment_status'];
				$payment_status = ( $multi_reg && $data_source['cost'] == 0.00 ) ? "Completed" : $default_payment_status;
				$payment = '';
				
			}

			$times_sql = "SELECT ese.start_time, ese.end_time, e.start_date, e.end_date ";
			$times_sql .= "FROM " . EVENTS_START_END_TABLE . " ese ";
			$times_sql .= "LEFT JOIN " . EVENTS_DETAIL_TABLE . " e ON ese.event_id = e.id WHERE ";
			$times_sql .= "e.id=%d";
			if (!empty($data_source['start_time_id'])) {
				$times_sql .= " AND ese.id=" . absint($data_source['start_time_id']);
			}

			$times = $wpdb->get_results($wpdb->prepare( $times_sql, $event_id ));
			foreach ($times as $time) {
				$start_time		= $time->start_time;
				$end_time		= $time->end_time;
				$start_date		= $time->start_date;
				$end_date		= $time->end_date;
			}


			//If we are using the number of attendees dropdown, add that number to the DB
			//echo $data_source['espresso_addtl_limit_dd'];
			if (isset($data_source['espresso_addtl_limit_dd'])) {
				$num_people = absint($data_source ['num_people']);
			} elseif (isset($event_meta['additional_attendee_reg_info']) && $event_meta['additional_attendee_reg_info'] == 1) {
				$num_people = absint($data_source ['num_people']);
			} else {
				$num_people = 1;
			}

			
			// check for coupon 
			if ( function_exists( 'event_espresso_process_coupon' )) {
				if ( $coupon_results = event_espresso_process_coupon( $event_id, $final_price, $multi_reg )) {
					//printr( $coupon_results, '$coupon_results  <br /><span style="font-size:10px;font-weight:normal;">' . __FILE__ . '<br />line no: ' . __LINE__ . '</span>', 'auto' );
					if ( $coupon_results['valid'] ) {
						$final_price = number_format( $coupon_results['event_cost'], 2, '.', '' );
						$coupon_code = $coupon_results['code'];
					}
					if ( ! $multi_reg && ! empty( $coupon_results['msg'] )) {
						$notifications['coupons'] = $coupon_results['msg'];
					}
				}					
			} 

			// check for groupon 
			if ( function_exists( 'event_espresso_process_groupon' )) {
				if ( $groupon_results = event_espresso_process_groupon( $event_id, $final_price, $multi_reg )) {
					//printr( $groupon_results, '$groupon_results  <br /><span style="font-size:10px;font-weight:normal;">' . __FILE__ . '<br />line no: ' . __LINE__ . '</span>', 'auto' );
					if ( $groupon_results['valid'] ) {
						$final_price = number_format( $groupon_results['event_cost'], 2, '.', '' );
						$coupon_code = $groupon_results['code'];
					}
					if ( ! $multi_reg && ! empty( $groupon_results['msg'] )) {
						$notifications['groupons'] = $groupon_results['msg'];
					}
				}					
			} 
			
			$start_time			= empty($start_time) ? '' : $start_time;
			$end_time			= empty($end_time) ? '' : $end_time;
			$start_date			= empty($start_date) ? '' : $start_date;
			$end_date			= empty($end_date) ? '' : $end_date;
			$organization_name	= empty($organization_name) ? '' : $organization_name;
			$country_id			= empty($country_id) ? '' : $country_id;
			$payment_date		= empty($payment_date) ? '' : $payment_date;
			$coupon_code		= empty($coupon_code) ? '' : $coupon_code;

			$amount_pd			= number_format( (float)$amount_pd, 2, '.', '' );
			$orig_price			= number_format( (float)$orig_price, 2, '.', '' );
			$final_price		= number_format( (float)$final_price, 2, '.', '' );
			$total_cost			= $total_cost + $final_price;

			$columns_and_values = array(
				'registration_id'		=> $registration_id,
				'is_primary'			=> $attendee_number == 1 ? TRUE : FALSE,
				'attendee_session'		=> $_SESSION['espresso_session']['id'],
				'lname'					=> $lname,
				'fname'					=> $fname,
				'address'				=> $address,
				'address2'				=> $address2,
				'city'					=> $city,
				'state'					=> $state,
				'zip'					=> $zip,
				'email'					=> $email,
				'phone'					=> $phone,
				'payment'				=> $payment,
				'txn_type'				=> $txn_type,
				'coupon_code'			=> $coupon_code,
				'event_time'			=> $start_time,
				'end_time'				=> $end_time,
				'start_date'			=> $start_date,
				'end_date'				=> $end_date,
				'price_option'			=> $price_type,
				'organization_name'		=> $organization_name,
				'country_id'			=> $country_id,
				'payment_status'		=> $payment_status,
				'payment_date'			=> $payment_date,
				'event_id'				=> $event_id,
				'quantity'				=> (int)$num_people,
				'amount_pd'				=> $amount_pd,
				'orig_price'			=> $orig_price,
				'final_price'			=> $final_price,
				'room_id'				=> $room_id
			);
			

			$data_formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%f' , '%s');

			// save the attendee details - FINALLY !!!
			if ( ! $wpdb->insert( EVENTS_ATTENDEE_TABLE, $columns_and_values, $data_formats )) {
				$error = true;
			}

			$attendee_id = $wpdb->insert_id;
			
			//Save the attendee data as a meta value
			do_action('action_hook_espresso_save_attendee_meta', $attendee_id, 'original_attendee_details', serialize($columns_and_values));
			
			// save attendee id for the primary attendee
			$primary_att_id = $attendee_number == 1 ? $attendee_id : FALSE;


			// Added for seating chart addon
			$booking_id = 0;
			if (defined('ESPRESSO_SEATING_CHART')) {
				if (seating_chart::check_event_has_seating_chart($event_id) !== false) {
					if (isset($_POST['seat_id'])) {
						$booking_id = seating_chart::parse_booking_info(sanitize_text_field($_POST['seat_id']));
						if ($booking_id > 0) {
							seating_chart::confirm_a_seat($booking_id, $attendee_id);
						}
					}
				}
			}
			
			//Add a record for the primary attendee
			if ( $attendee_number == 1 ) {
				
				$columns_and_values = array(
					'attendee_id'	=> $primary_att_id,
					'meta_key'		=> 'primary_attendee',
					'meta_value'	=> 1
				);
				$data_formats = array('%s', '%s', '%s');
			
				if ( !$wpdb->insert(EVENTS_ATTENDEE_META_TABLE, $columns_and_values, $data_formats) ) {
					$error = true;
				}

			}


			if (defined('EVENTS_MAILCHIMP_ATTENDEE_REL_TABLE') && $espresso_premium == true) {
				MailChimpController::list_subscribe($event_id, $attendee_id, $fname, $lname, $email);
			}

			//Defining the $base_questions variable in case there are no additional attendee questions
			$base_questions = $questions;

			//Since main attendee and additional attendees may have different questions,
			//$attendee_number check for 2 because is it statically set at 1 first and is incremented for the primary attendee above, hence 2
			$questions = ( $attendee_number > 1 && isset($event_meta['add_attendee_question_groups'])) ? $event_meta['add_attendee_question_groups'] : $questions;

			add_attendee_questions( $questions, $registration_id, $attendee_id, array( 'session_vars' => $att_data_source ));
			
			//Add additional attendees to the database
			if ($event_meta['additional_attendee_reg_info'] > 1) {
			
				$questions = $event_meta['add_attendee_question_groups'];

				if (empty($questions)) {
					$questions = $base_questions;
				}


				if ( isset( $att_data_source['x_attendee_fname'] )) {
					foreach ( $att_data_source['x_attendee_fname'] as $k => $v ) {
					
						if ( trim($v) != '' && trim( $att_data_source['x_attendee_lname'][$k] ) != '' ) {

							// Added for seating chart addon
							$seat_check = true;
							$x_booking_id = 0;
							if ( defined('ESPRESSO_SEATING_CHART')) {
								if (seating_chart::check_event_has_seating_chart($event_id) !== false) {
									if (!isset($att_data_source['x_seat_id'][$k]) || trim($att_data_source['x_seat_id'][$k]) == '') {
										$seat_check = false;
									} else {
										$x_booking_id = seating_chart::parse_booking_info($att_data_source['x_seat_id'][$k]);
										if ($x_booking_id > 0) {
											$seat_check = true;
											$price_type =  $att_data_source['x_seat_id'][$k];
											$final_price = seating_chart::get_purchase_price($att_data_source['x_seat_id'][$k]);
											$orig_price = $final_price;
										} else {
											$seat_check = false; //Keeps the system from adding an additional attndee if no seat is selected
										}
									}
								}
							}
							
							if ($seat_check) {

								$ext_att_data_source = array(
									'registration_id'	=> $registration_id,
									'attendee_session'	=> $_SESSION['espresso_session']['id'],
									'lname'				=> sanitize_text_field($att_data_source['x_attendee_lname'][$k]),
									'fname'				=> sanitize_text_field($v),
									'email'				=> sanitize_text_field($att_data_source['x_attendee_email'][$k]),
									'address'			=> empty($att_data_source['x_attendee_address'][$k]) ? '' : sanitize_text_field($att_data_source['x_attendee_address'][$k]),
									'address2'			=> empty($att_data_source['x_attendee_address2'][$k]) ? '' : sanitize_text_field($att_data_source['x_attendee_address2'][$k]),
									'city'				=> empty($att_data_source['x_attendee_city'][$k]) ? '' : sanitize_text_field($att_data_source['x_attendee_city'][$k]),
									'state'				=> empty($att_data_source['x_attendee_state'][$k]) ? '' : sanitize_text_field($att_data_source['x_attendee_state'][$k]),
									'zip'				=> empty($att_data_source['x_attendee_zip'][$k]) ? '' : sanitize_text_field($att_data_source['x_attendee_zip'][$k]),
									'phone'				=> empty($att_data_source['x_attendee_phone'][$k]) ? '' : sanitize_text_field($att_data_source['x_attendee_phone'][$k]),
									'payment'			=> $payment,
									'event_time'		=> $start_time,
									'end_time'			=> $end_time,
									'start_date'		=> $start_date,
									'end_date'			=> $end_date,
									'price_option'		=> $price_type,
									'organization_name'	=> $organization_name,
									'country_id'		=> $country_id,
									'payment_status'	=> $payment_status,
									'payment_date'		=> $payment_date,
									'event_id'			=> $event_id,
									'quantity'			=> (int)$num_people,
									'amount_pd'			=> 0.00,
									'orig_price'		=> $orig_price,
									'final_price'		=> $final_price										
								);
								
								$format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%f' );
								$wpdb->insert( EVENTS_ATTENDEE_TABLE, $ext_att_data_source, $format );
								
								//Added by Imon
								$ext_attendee_id = $wpdb->insert_id;
								
								//Save the attendee data as a meta value
								do_action('action_hook_espresso_save_attendee_meta', $ext_attendee_id, 'original_attendee_details', serialize($ext_att_data_source));
			
								$mailchimp_attendee_id = $ext_attendee_id;

								if (defined('EVENTS_MAILCHIMP_ATTENDEE_REL_TABLE') && $espresso_premium == true) {
									MailChimpController::list_subscribe($event_id, $mailchimp_attendee_id, $v, $att_data_source['x_attendee_lname'][$k], $att_data_source['x_attendee_email'][$k]);
								}
								
								if ( ! is_array($questions) && !empty($questions)) {
									$questions = unserialize($questions);
								}

								$questions_in = '';
								foreach ($questions as $g_id) {
									$questions_in .= $g_id . ',';
								}
								$questions_in = substr($questions_in, 0, -1);

								$SQL = "SELECT q.*, qg.group_name FROM " . EVENTS_QUESTION_TABLE . " q ";
								$SQL .= "JOIN " . EVENTS_QST_GROUP_REL_TABLE . " qgr on q.id = qgr.question_id ";
								$SQL .= "JOIN " . EVENTS_QST_GROUP_TABLE . " qg on qg.id = qgr.group_id ";
								$SQL .= "WHERE qgr.group_id in ( $questions_in ) ";
								$SQL .= "ORDER BY q.id ASC";
								
								$questions_list = $wpdb->get_results($wpdb->prepare( $SQL, NULL ));
								foreach ($questions_list as $question_list) {
									if ($question_list->system_name != '') {
										$ext_att_data_source[$question_list->system_name] = $att_data_source['x_attendee_' . $question_list->system_name][$k];
									} else {
										$ext_att_data_source[$question_list->question_type . '_' . $question_list->id] = isset($att_data_source['x_attendee_' . $question_list->question_type . '_' . $question_list->id][$k]) && !empty($att_data_source['x_attendee_' . $question_list->question_type . '_' . $question_list->id][$k]) ? $att_data_source['x_attendee_' . $question_list->question_type . '_' . $question_list->id][$k] : '';
									}
								}

								echo add_attendee_questions($questions, $registration_id, $ext_attendee_id, array('session_vars' => $ext_att_data_source));
								
							}
							
							// Added for seating chart addon
							if (defined('ESPRESSO_SEATING_CHART')) {
								if (seating_chart::check_event_has_seating_chart($event_id) !== false && $x_booking_id > 0) {
									seating_chart::confirm_a_seat($x_booking_id, $ext_attendee_id);
								}
							}
						}
					}
				}
			}


			//Add member data if needed
			if (defined('EVENTS_MEMBER_REL_TABLE')) {
				require_once(EVENT_ESPRESSO_MEMBERS_DIR . "member_functions.php"); //Load Members functions
				require(EVENT_ESPRESSO_MEMBERS_DIR . "user_vars.php"); //Load Members functions
				if ($userid != 0) {
					event_espresso_add_user_to_event( $event_id, $userid, $attendee_id );
				}
			}

			$attendee_number++;

			if (isset($data_source['admin'])) {
				return $attendee_id;
			}
			

			//This shows the payment page
			if ( ! $multi_reg) {
				return events_payment_page( $attendee_id, $notifications ); 
			}
			
			return array( 'registration_id' => $registration_id, 'notifications' => $notifications );
						
		}		
}



/**
 * Workshops Registration shopping_cart.php
 */


if ( !function_exists( 'event_espresso_shopping_cart' ) ){

		function event_espresso_shopping_cart() {
			global $wpdb, $org_options;
			//session_destroy();
			//echo "<pre>", print_r( $_SESSION ), "</pre>";
			$events_in_session = isset( $_SESSION['espresso_session']['events_in_session'] ) ? $_SESSION['espresso_session']['events_in_session'] : event_espresso_clear_session( TRUE );
			
			if ( event_espresso_invoke_cart_error( $events_in_session ) )
				return false;

			if ( count( $events_in_session ) > 0 ){
				foreach ( $events_in_session as $event ) {
					// echo $event['id'];
					if ( is_numeric( $event['id'] ) )
						$events_IN[] = $event['id'];
				}

			$events_IN = implode( ',', $events_IN );

			$sql = "SELECT e.* FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sql = apply_filters( 'filter_hook_espresso_shopping_cart_SQL_select', $sql );
			$sql .= " WHERE e.id in ($events_IN) ";
			$sql .= " AND e.event_status != 'D' ";
			$sql .= " ORDER BY e.start_date ";

			$result = $wpdb->get_results( $sql );	
		
			$sqlW1 = "SELECT e.* FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sqlW1 = apply_filters( 'filter_hook_espresso_shopping_cart_SQL_select', $sqlW1 );
			$sqlW1 .= " WHERE e.id in ($events_IN) ";
			$sqlW1 .= " AND e.event_status != 'D' ";
			$sqlW1 .= " AND e.id IN (20,21) "; //change event_id if necessary
			$resultW1 = $wpdb->get_results( $sqlW1 );

			$sqlW2 = "SELECT e.* FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sqlW2 = apply_filters( 'filter_hook_espresso_shopping_cart_SQL_select', $sqlW2 );
			$sqlW2 .= " WHERE e.id in ($events_IN) ";
			$sqlW2 .= " AND e.event_status != 'D' ";
			$sqlW2 .= " AND e.id IN (22,23) ";
			$resultW2 = $wpdb->get_results( $sqlW2 );

			$sqlW3 = "SELECT e.* FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sqlW3 = apply_filters( 'filter_hook_espresso_shopping_cart_SQL_select', $sqlW3 );
			$sqlW3 .= " WHERE e.id in ($events_IN) ";
			$sqlW3 .= " AND e.event_status != 'D' ";
			$sqlW3 .= " AND e.id IN (24,25) ";
			$resultW3 = $wpdb->get_results( $sqlW3 );

			$sqlW4 = "SELECT e.* FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sqlW4 = apply_filters( 'filter_hook_espresso_shopping_cart_SQL_select', $sqlW4 );
			$sqlW4 .= " WHERE e.id in ($events_IN) ";
			$sqlW4 .= " AND e.event_status != 'D' ";
			$sqlW4 .= " AND e.id IN (26,27) ";
			$resultW4 = $wpdb->get_results( $sqlW4 );		

			$sqlAllOther = "SELECT e.* FROM " . EVENTS_DETAIL_TABLE . " e ";
			$sqlAllOther = apply_filters( 'filter_hook_espresso_shopping_cart_SQL_select', $sqlAllOther );
			$sqlAllOther .= " WHERE e.id in ($events_IN) ";
			$sqlAllOther .= " AND e.event_status != 'D' ";
			$sqlAllOther .= " AND e.id IN (28,29) ";
			$resultAllOther = $wpdb->get_results( $sqlAllOther );	
			
?>

<form action='?page_id=<?php echo $org_options['event_page_id']; ?>&regevent_action=load_checkout_page' method='post' id="event_espresso_shopping_cart">

<?php
		$counter = 1; //Counter that will keep track of the first events
		foreach ( $result as $r ){
			
			//Check to see if the Members plugin is installed.
			if ( function_exists('espresso_members_installed') && espresso_members_installed() == true && !is_user_logged_in() ) {
				$member_options = get_option('events_member_settings');
				if ($r->member_only == 'Y' || $member_options['member_only_all'] == 'Y'){
					event_espresso_user_login();
					return;
				}
			}
			//If the event is still active, then show it.
			if (event_espresso_get_status($r->id) == 'ACTIVE') {
				$num_attendees = get_number_of_attendees_reg_limit( $r->id, 'num_attendees' ); //Get the number of attendees
				$available_spaces = get_number_of_attendees_reg_limit( $r->id, 'available_spaces' ); //Gets a count of the available spaces
				$number_available_spaces = get_number_of_attendees_reg_limit( $r->id, 'number_available_spaces' ); //Gets the number of available spaces
				//echo "<pre>$r->id, $num_attendees,$available_spaces,$number_available_spaces</pre>";
		?>
				<div class="multi_reg_cart_block event-display-boxes ui-widget"  id ="multi_reg_cart_block-<?php echo $r->id ?>">
		
					<h3 class="event_title ui-widget-header ui-corner-top"><?php echo stripslashes_deep( $r->event_name ) ?> <span class="remove-cart-item"> <img class="ee_delete_item_from_cart" id="cart_link_<?php echo $r->id ?>" alt="Remove this item from your cart" src="<?php echo EVENT_ESPRESSO_PLUGINFULLURL ?>images/icons/remove.gif" /> </span> </h3>
						<div class="event-data-display ui-widget-content ui-corner-bottom">
							<table id="cart-reg-details" class="event-display-tables">
								<thead>
									<tr>
										<th><?php _e( 'Date', 'event_espresso' ); ?></th>
										<th><?php _e( 'Time', 'event_espresso' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>
											<?php echo event_date_display( $r->start_date, get_option( 'date_format' ) ) ?>
											<?php /*_e( ' to ', 'event_espresso' ); ?> <?php echo event_date_display( $r->end_date, get_option( 'date_format' ) )*/ ?>
										</td>
										<td>
											<?php echo event_espresso_time_dropdown( $r->id, 0, 1, $_SESSION['espresso_session']['events_in_session'][$r->id]['start_time_id'] ); ?>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<?php echo event_espresso_group_price_dropdown( $r->id, 0, 1, $_SESSION['espresso_session']['events_in_session'][$r->id]['price_id']); ?>
										</td>
									</tr>
								</tbody>
							</table>
		
						<input type="hidden" name="event_name[<?php echo $r->id; ?>]" value="<?php echo $r->event_name; ?>" />
						<input type="hidden" name="use_coupon[<?php echo $r->id; ?>]" value="<?php echo $r->use_coupon_code; ?>" />
						<input type="hidden" name="use_groupon[<?php echo $r->id; ?>]" value="<?php echo $r->use_groupon_code; ?>" />
						<?php do_action_ref_array( 'action_hook_espresso_add_to_multi_reg_cart_block', array( $r ) ); ?>
						
					</div><!-- / .event-data-display -->
				</div><!-- / .event-display-boxes -->
		
				<?php
				$counter++;
			}			
?>				
						
<?php		}
		//echo $_SESSION['espresso_session']['groupon_used'];
//		printr( $_SESSION, '$_SESSION  <br /><span style="font-size:10px;font-weight:normal;">' . __FILE__ . '<br />line no: ' . __LINE__ . '</span>', 'auto' );
		?>
		
		<div class="event-display-boxes ui-widget">
			<div class="mer-event-submit ui-widget-content ui-corner-all">
				<input type="hidden" name="event_name[<?php echo $r->id; ?>]" value="<?php echo stripslashes_deep( $r->event_name ); ?>" />
				<input type="hidden" name="regevent_action" value="load_checkout_page" />
					
			<?php if ( function_exists( 'event_espresso_coupon_payment_page' ) && isset($org_options['allow_mer_discounts']) && $org_options['allow_mer_discounts'] == 'Y' ) : //Discount code display ?>
			<div id="event_espresso_coupon_wrapper" class="clearfix event-data-display">
				<label class="coupon-code" for="event_espresso_coupon_code"><?php _e( 'Enter Coupon Code ', 'event_espresso' ); ?></label>
				<input type="text" 
							name="event_espresso_coupon_code" 
							id ="event_espresso_coupon_code" 
							value="<?php echo isset( $_SESSION['espresso_session']['event_espresso_coupon_code'] ) ? $_SESSION['espresso_session']['event_espresso_coupon_code'] : ''; ?>"
							onkeydown="if(event.keyCode==13) {document.getElementById('event_espresso_refresh_total').focus(); return false;}" 
						/>
			</div>
			<?php endif; ?>
			
			<?php if ( function_exists( 'event_espresso_groupon_payment_page' ) && isset($org_options['allow_mer_vouchers']) && $org_options['allow_mer_vouchers'] == 'Y' ) : //Voucher code display ?>
			<div id="event_espresso_coupon_wrapper" class="clearfix event-data-display" >
				<label class="coupon-code" for="event_espresso_groupon_code"><?php _e( 'Enter Voucher Code ', 'event_espresso' ); ?></label>
				<input type="text" 
							name="event_espresso_groupon_code" 
							id ="event_espresso_groupon_code" 
							value="<?php echo isset( $_SESSION['espresso_session']['groupon_code'] ) ? $_SESSION['espresso_session']['groupon_code'] : ''; ?>"
							onkeydown="if(event.keyCode==13) {document.getElementById('event_espresso_refresh_total').focus(); return false;}" 
						/>
			</div>
			<?php endif; ?>
			
             <div id="event_espresso_notifications" class="clearfix event-data-display" style=""></div> 			

<?php

$countW1 = count($resultW1);
$countW2 = count($resultW2);
$countW3 = count($resultW3);
$countW4 = count($resultW4);
$countAllOther = count($resultAllOther);

if ($countW1 > 1 || $countW2 > 1 || $countW3 > 1 || $countW4 > 1) {
?>		
			<div id="event_espresso_total_wrapper" class="clearfix event-data-display">						
				<p>

<script>
function myFunction()
{
alert("You have 2 or more workshops/discussion in a session. Please use the red minus button on the top right of each session above to remove the extra events and then refresh this page to be able to proceed to the next page.");
}
</script>
							
			<div class='infopane color-8'><div class='inner' style='color:red;'>You have 2 or more workshops/discussion in a session. Please use the red minus button on the top right of each session above to remove the extra events and then <strong><a href="javascript:location.reload(true);">Refresh</a></strong> this page to be able to proceed to the next page</div></div>
						
			</p>
			</div>
			<p id="event_espresso_submit_cart">
				<input type="button" class="submit btn_event_form_submit submit_grayed ui-priority-primary ui-state-default ui-state-hover ui-state-focus ui-corner-all" name="Continue" onclick="location.href='javascript:location.reload(true);'" value="<?php _e( 'Refresh Page', 'event_espresso' ); ?>" />
			</p>
			
			<p id="event_espresso_submit_cart">
			
				<input type="button" class="submit btn_event_form_submit submit_grayed button ft_button btn_middle btn_sharp" name="Continue" id="event_espresso_continue_registration" onclick="myFunction()" value="<?php _e( 'Enter Attendee Information', 'event_espresso' ); ?>&nbsp;&raquo;" />
				&nbsp;or <a href="http://conference.unavsa.org/event-registration/registration-canceled/">Cancel and Start Over</a>
				
			</p>

<?php } else { ?>

			<div id="event_espresso_total_wrapper" class="clearfix event-data-display">	
					
				<?php do_action( 'action_hook_espresso_shopping_cart_before_total' ); ?>				
				<span class="event_total_price">
					<?php _e( 'Total ', 'event_espresso' ) . $org_options['currency_symbol'];?> <span id="event_total_price"><?php echo $_SESSION['espresso_session']['grand_total'];?></span>
				</span>
				<?php do_action( 'action_hook_espresso_shopping_cart_after_total' ); ?>
				<p id="event_espresso_refresh_total">
				<a id="event_espresso_refresh_total" style="cursor:pointer;"><?php _e( 'Refresh Total', 'event_espresso' ); ?></a>
			</p>
			</div>			
			<p id="event_espresso_submit_cart">
				<input type="submit" class="submit btn_event_form_submit button ft_button btn_middle btn_sharp" name="Continue" id="event_espresso_continue_registration" value="<?php _e( 'Enter Attendee Information', 'event_espresso' ); ?>&nbsp;&raquo;" />
				&nbsp;or <a href="http://conference.unavsa.org/event-registration/registration-canceled/">Cancel and Start Over</a>
			</p>
<?php } ?>
			
		</div><!-- / .mer-event-submit -->
	</div><!-- / .event-display-boxes -->
</form>

<?php if ($countAllOther < 1) { ?>
<script>jQuery(document).ready(function(){
    jQuery(".price_id").val(1);
    jQuery('#event_espresso_refresh_total').trigger('click');
});
</script>
<?php } ?>


<?php
			}
		}
}


?>