<?php

/*************************************************************************************
 *	Show Hotel and Workshops only to Paid attendees
 *  Also give message if the user has paid
 *  Disable Form after user completed a Registration
 *************************************************************************************/

add_filter('the_content','show_only_for_paid',10);

function show_only_for_paid($content) {
global $userdata;
    get_currentuserinfo();

$new_content=$content;
$warn_content=$content;
$warn_messages="";
$progress="";

if ( is_page(array('workshop-registration','hotel-registration','my-events','profile','register','event-registration','extra-options','Event Registration Form','login','register','create-profile','Registration Canceled','Thank You','Registration Support','test-page','workshop-test-page') ) ) {

// START filtering logic
	global $current_user, $wpdb;	
	// Get the member
	$member = wp_get_current_user();

	$wpdb->get_results("SELECT id FROM ". EVENTS_MEMBER_REL_TABLE . " WHERE user_id = '" . $current_user->ID . "'");
	//Conference logic
	$conf_paid_query = "SELECT a.payment_status, a.event_id FROM " . EVENTS_ATTENDEE_TABLE . " a "; 
	$conf_paid_query .= "JOIN " . EVENTS_MEMBER_REL_TABLE . " u ON u.attendee_id = a.id ";
	$conf_paid_query .= "JOIN " . EVENTS_DETAIL_TABLE . " e ON e.id = u.event_id ";
	$conf_paid_query .= "WHERE u.user_id = '" . $current_user->ID . "' AND a.event_id IN (88)";
	$conference_paid = $wpdb->get_results($wpdb->prepare($conf_paid_query, null));
	//then use an array to keep the payment status for each event id
	$conference_paid_ID=array();	
	foreach ($conference_paid as $conference_res) {
		$conference_paid_ID[intval($conference_res->event_id)]=$conference_res->payment_status;
	}

	//Hotel logic
	$hotel_paid_query = "SELECT a.payment_status, a.event_id FROM " . EVENTS_ATTENDEE_TABLE . " a ";
	$hotel_paid_query .= "JOIN " . EVENTS_MEMBER_REL_TABLE . " u ON u.attendee_id = a.id ";
	$hotel_paid_query .= "JOIN " . EVENTS_DETAIL_TABLE . " e ON e.id = u.event_id ";
	$hotel_paid_query .= "WHERE u.user_id = '" . $current_user->ID . "' AND a.event_id IN (91)";
	$hotel_paid = $wpdb->get_results($wpdb->prepare($hotel_paid_query, null));		
	//then use an array to keep the payment status for each event id
	$payment4EventId=array();	
	foreach ($hotel_paid as $hotel_res) {
		$payment4EventId[intval($hotel_res->event_id)]=$hotel_res->payment_status;
	}

	//Workshops logic
	$W1_query = "SELECT a.payment_status, a.event_id FROM " . EVENTS_ATTENDEE_TABLE . " a ";
	$W1_query .= "JOIN " . EVENTS_MEMBER_REL_TABLE . " u ON u.attendee_id = a.id ";
	$W1_query .= "JOIN " . EVENTS_DETAIL_TABLE . " e ON e.id = u.event_id ";
	$W1_query .= "WHERE u.user_id = '" . $current_user->ID . "' AND a.event_id IN (95,96,97,98,99,100)";
	$W1_completed = $wpdb->get_results($wpdb->prepare($W1_query, null));		
	//then use an array to keep the payment status for each event id
	$completed4W1=array();	
	foreach ($W1_completed as $W1_com) {
		$completed4W1[intval($W1_com->event_id)]=$W1_com->payment_status;
	}

	// Logic for photo upload requirement. Since UNAVSA-12 due to change in photo management
	$user_id = $current_user->ID;
	$single = true;
	$uploaded_photo = get_user_meta($user_id, 'resized_avatar_17', $single);
	$event_hashtag = do_shortcode('[EE_META type="event_meta" name="event_hashtag"]');

	if ( is_page(array('event-registration','Event Registration Form')) && is_user_logged_in() ) {
		if ($uploaded_photo === '' || $uploaded_photo === NULL) {
				$new_content="<p>[notification type='notification_warning']<strong>Photo not uploaded:</strong> Please go to your <a href='/profile'>Profile and upload a photo</a> before continuing. Once uploaded, come back to this page [Step 2] to Register for Conference[/notification]</p>";
				$new_content.="<p><a href='/profile' class='button ft_button btn_middle btn_sharp' style='float:left;color:white;'>Go to your Profile</a></p><p>&nbsp;</p><p>&nbsp;</p>";
				$new_content.="<p><h4>Register for UNAVSA-12 Conference</h4></p>";
				$new_content.="<p><img src='http://conference.unavsa.org/images/no_photo.jpg' style='max-width:703px;max-height:516px;'></p>";
				$new_content.="<p>[notification type='notification_warning']<strong>Photo not uploaded:</strong> Please go to your <a href='/profile'>Profile and upload a photo</a> before continuing. Once uploaded, come back to this page [Step 2] to Register for Conference[/notification]</p>";

				} else {
				$new_content = $content;
			}		
	}

	// START page restrictions. Users must pay for conference first to get to hotel, workshop, and extra
    if ( is_page('hotel-registration') ) {
		if ($conference_paid_ID[88] === 'Completed' || $conference_paid_ID[93] === 'Completed') {
				$new_content = $content;
			} else {
				$new_content="<p>[notification type='notification_warning']Please register for the Conference first before registering for hotels. <a href=/event-registration/>Register for the Conference</a>[/notification]</p>";
			}		
	}

	if ( is_page(array('workshop-registration','workshop-test-page') ) ) {
		if ($conference_paid_ID[88] === 'Completed' || $conference_paid_ID[93] === 'Completed') {
				$new_content = $content;
			} else {
				$new_content="<p>[notification type='notification_warning']Please register for the Conference first before registering for workshops. <a href=/event-registration/>Register for the Conference</a>[/notification]</p>";
			}		
	}
	
	if ( is_page('extra-options') ) {
		if ($conference_paid_ID[88] === 'Completed' || $conference_paid_ID[93] === 'Completed') {
				$new_content = $content;
			} else {
				$new_content="<p>[notification type='notification_warning']Please register for the Conference first before registering for extras like pre/post-conference tours and breakfast options. <a href=/event-registration/>Register for the Conference</a>[/notification]</p>";
			}		
	}
// END page restrictions

// START warning messages
	if ( is_page(array('workshop-registration','hotel-registration','my-events','profile','register','event-registration','extra-options','Event Registration Form','login','register','create-profile','Registration Canceled','Thank You','Registration Support','test-page') ) ) {
						
		if ($payment4EventId[91] === 'Completed') {
			$message_warn_line="<div class='infopane color-8'><div class='inner' style='background-image:none;'>[notification type='notification_info']You're registered for a hotel! <a href=/my-events>Give your Room ID</a> to your friends to be in the same room![/notification] </div></div>";
			$warn_messages.=$message_warn_line;	
		}
	}
	
	$event_hashtag = do_shortcode('[EE_META type="event_meta" name="event_hashtag"]');
	if ( is_page('event-registration') || is_page('Event Registration Form') && $event_hashtag === 'conference' ) {
						
		if ($conference_paid_ID[88] === 'Completed' || $conference_paid_ID[93] === 'Completed') {
//			$message_warn_line='<div id="message" class="completion">You\'ve registered for Conference! Visit the <a href=/my-events>My Events page</a> to see the details of your registration.</div>';
//			$warn_messages.=$message_warn_line;
			$new_content="<p><div class='infopane color-8'><div class='inner' style='background-image:none;'>[notification type='notification_info']You're registered for conference! View your <a href='/my-events'>My Events</a> for your registration details.[/notification] </div></div><br><p><a href='/my-events'>Go to My Events</a> <a href='/hotel-registration/' class='button ft_button btn_middle btn_sharp' style='float:right;color:white;'>Next Step - Register for Hotel</a></p><p>&nbsp;</p><p> </p>";
//			$progress_text1 = '<li>Conference<br />Registered</li>';
		} else if ($conference_paid_ID[88] === 'Incomplete' || $conference_paid_ID[93] === 'Incomplete' || $conference_paid_ID[88] === 'Payment Declined' || $conference_paid_ID[93] === 'Payment Declined') {
//			$message_warn_line='<div id="message" class="completion">You\'ve registered for Conference! Visit the <a href=/my-events>My Events page</a> to pay for your registration.</div>';
//			$warn_messages.=$message_warn_line;
			$new_content="<p><div class='infopane color-4'><div class='inner' style='background-image:none;'>[notification type='notification_warning']You've completed registration for conference, but haven't paid yet. Please visit <a href='/my-events'>My Events</a> to pay for Conference.[/notification] </div></div><p><a href='/my-events'>Go to My Events</a></p><p>&nbsp;</p><p> </p>";
//			$progress_text1 = '<li>Conference<br />Registered</li>';		
		} else if ($conference_paid_ID[88] === 'Pending' || $conference_paid_ID[93] === 'Pending') {
//			$message_warn_line='<div id="message" class="completion">You\'ve registered for Conference! Visit the <a href=/my-events>My Events page</a> to pay for your registration.</div>';
//			$warn_messages.=$message_warn_line;
			$new_content="<p><div class='infopane color-4'><div class='inner' style='background-image:none;'>[notification type='notification_warning']You've completed registration for conference, but your payment is currently Pending. Please view your <a href='/my-events'>My Events</a> to check the status if your payment.[/notification] </div></div><p><a href='/my-events'>Go to My Events</a></p><p>&nbsp;</p><p> </p>";
//			$progress_text1 = '<li>Conference<br />Registered</li>';
		}
	}
	
	$workshop_meta = do_shortcode('[EE_META type="event_meta" name="workshop_meta"]');
	if ( is_page('event-registration') || is_page('Event Registration Form') && $workshop_meta === 'W1' ) {		
	
		if ($completed4W1[95] === 'Completed' || $completed4W1[96] === 'Completed') {
			$new_content="<p><div class='infopane color-8'><div class='inner' style='background-image:none;'>[notification type='notification_info']You're already registered in a workshop for Session 1! If you want to change to another workshop, please go to your <a href='/my-events'>My Events</a> page and remove your current registered workshop for Session 1.[/notification] </div></div><br><p><a href='/my-events'>Go to My Events</a> <a href='/extra-options/' class='button ft_button btn_middle btn_sharp' style='float:right;color:white;'>Next Step - Register for Extra Options</a></p><p>&nbsp;</p><p> </p>";
		} 
	}
	
	if ( is_page('event-registration') || is_page('Event Registration Form') && $workshop_meta === 'W2' ) {	
	
		if ($completed4W1[97] === 'Completed' || $completed4W1[98] === 'Completed') {
			$new_content="<p><div class='infopane color-8'><div class='inner' style='background-image:none;'>[notification type='notification_info']You're already registered in a workshop for Session 2! If you want to change to another workshop, please go to your <a href='/my-events'>My Events</a> page and remove your current registered workshop for Session 2.[/notification] </div></div><br><p><a href='/my-events'>Go to My Events</a> <a href='/extra-options/' class='button ft_button btn_middle btn_sharp' style='float:right;color:white;'>Next Step - Register for Extra Options</a></p><p>&nbsp;</p><p> </p>";
		} 
	}
	
	if ( is_page('event-registration') || is_page('Event Registration Form') && $workshop_meta === 'W3' ) {	
	
		if ($completed4W1[99] === 'Completed' || $completed4W1[100] === 'Completed') {
			$new_content="<p><div class='infopane color-8'><div class='inner' style='background-image:none;'>[notification type='notification_info']You're already registered in a discussion for this Session 3! If you want to change to another discussion, please go to your <a href='/my-events'>My Events</a> page and remove your current registered discussion for Session 3.[/notification] </div></div><br><p><a href='/my-events'>Go to My Events</a> <a href='/extra-options/' class='button ft_button btn_middle btn_sharp' style='float:right;color:white;'>Next Step - Register for Extra Options</a></p><p>&nbsp;</p><p> </p>";
		} 
	}

	if ( is_page('event-registration') || is_page('Event Registration Form') && $workshop_meta === 'W4' ) {	
	
		if ($completed4W1[101] === 'Completed' || $completed4W1[102] === 'Completed') {
			$new_content="<p><div class='infopane color-8'><div class='inner' style='background-image:none;'>[notification type='notification_info']You're already registered in a discussion for this Session 4! If you want to change to another discussion, please go to your <a href='/my-events'>My Events</a> page and remove your current registered discussion for Session 3.[/notification] </div></div><br><p><a href='/my-events'>Go to My Events</a> <a href='/extra-options/' class='button ft_button btn_middle btn_sharp' style='float:right;color:white;'>Next Step - Register for Extra Options</a></p><p>&nbsp;</p><p> </p>";
		} 
	}
	
// End warning messages
// 
	}	
	$warn_content = $warn_messages . " " . $new_content;
	return $warn_content;
}


/*************************************************************************************
 *	Show Progress Bar
 *************************************************************************************/

add_filter( 'init','show_title_for_paid', 10 );

function show_title_for_paid() {
global $userdata;
    get_currentuserinfo();


if ( is_page(array('workshop-registration','hotel-registration','my-events','profile','register','event-registration','extra-options','Event Registration Form','event-registration-form','login','register','create-profile','Registration Canceled','Thank You','Registration Support','how-to-register-for-conference','waiver','refund-policy','conference-map','conference-map-canada','conference-map-world','conference-map-states','test-page','workshop-test-page') ) ) {

// START filtering logic
	global $current_user, $wpdb;	
	// Get the member
	$member = wp_get_current_user();

	$wpdb->get_results("SELECT id FROM ". EVENTS_MEMBER_REL_TABLE . " WHERE user_id = '" . $current_user->ID . "'");

	$conf_paid_query = "SELECT a.payment_status, a.event_id FROM " . EVENTS_ATTENDEE_TABLE . " a "; 
	$conf_paid_query .= "JOIN " . EVENTS_MEMBER_REL_TABLE . " u ON u.attendee_id = a.id ";
	$conf_paid_query .= "JOIN " . EVENTS_DETAIL_TABLE . " e ON e.id = u.event_id ";
	$conf_paid_query .= "WHERE u.user_id = '" . $current_user->ID . "' AND a.event_id IN (88,93)";
	$conference_paid = $wpdb->get_results($wpdb->prepare($conf_paid_query, null));
	//then use an array to keep the payment status for each event id
	$conference_paid_ID=array();	
	foreach ($conference_paid as $conference_res) {
		$conference_paid_ID[intval($conference_res->event_id)]=$conference_res->payment_status;
	}
	
	$hotel_paid_query = "SELECT a.payment_status, a.event_id FROM " . EVENTS_ATTENDEE_TABLE . " a ";
	$hotel_paid_query .= "JOIN " . EVENTS_MEMBER_REL_TABLE . " u ON u.attendee_id = a.id ";
	$hotel_paid_query .= "JOIN " . EVENTS_DETAIL_TABLE . " e ON e.id = u.event_id ";
	$hotel_paid_query .= "WHERE u.user_id = '" . $current_user->ID . "' AND a.event_id IN (91)";
	$hotel_paid = $wpdb->get_results($wpdb->prepare($hotel_paid_query, null));	

	//then use an array to keep the payment status for each event id
	$payment4EventId=array();	
	foreach ($hotel_paid as $hotel_res) {
		$payment4EventId[intval($hotel_res->event_id)]=$hotel_res->payment_status;
	}
	
	$workshop_paid_query = "SELECT a.payment_status, a.event_id FROM " . EVENTS_ATTENDEE_TABLE . " a ";
	$workshop_paid_query .= "JOIN " . EVENTS_MEMBER_REL_TABLE . " u ON u.attendee_id = a.id ";
	$workshop_paid_query .= "JOIN " . EVENTS_DETAIL_TABLE . " e ON e.id = u.event_id ";
	$workshop_paid_query .= "WHERE u.user_id = '" . $current_user->ID . "' AND a.event_id IN (95,96,97,98,99,100)";
	$workshop_paid = $wpdb->get_results($wpdb->prepare($workshop_paid_query, null));	

	$payment4WorkshopId=array();		
	foreach ($workshop_paid as $workshop_res) {
		$payment4WorkshopId[intval($workshop_res->event_id)]=$workshop_res->payment_status;
	}

// START warning messages
	if ( is_page(array('workshop-registration','hotel-registration','my-events','profile','register','event-registration','extra-options','Event Registration Form','event-registration-form','login','register','create-profile','Registration Canceled','Thank You','Registration Support','how-to-register-for-conference','waiver','refund-policy','conference-map','conference-map-canada','conference-map-world','conference-map-states','test-page','workshop-test-page') ) ) {

		if (is_user_logged_in()) {
			$progressClassProfile = 'grayed';
			$progessMessageProfile = '<br /><h10 style="color:#279161">Completed!</h10>';
			$progressUrlProfile = '/profile';
		} else {
			$progressUrlProfile = '/create-profile';
		}
		
		if ($conference_paid_ID[88] === 'Completed' || $conference_paid_ID[93] === 'Completed') {
			$progressClassConference = 'grayed';
			$progessMessageConference = '<br /><h10 style="color:#279161">Completed!</h10>';
			$progressUrlConference = '/event-registration';
		} else if ($conference_paid_ID[88] === 'Incomplete' || $conference_paid_ID[93] === 'Incomplete' || $conference_paid_ID[88] === 'Payment Declined' || $conference_paid_ID[93] === 'Payment Declined') {
			$progessMessageConference = '<br /><h10 style="color:#b40000">Incomplete</h10>';
			$progressUrlConference = '/my-events';			
		} else if ($conference_paid_ID[88] === 'Pending' || $conference_paid_ID[93] === 'Pending') {
			$progessMessageConference = '<br /><h10 style="color:#FFA500">Pending</h10>';
			$progressUrlConference = '/my-events';	
		} else {
			$progressUrlConference = '/event-registration';
		}
				
		if ($payment4EventId[91] === 'Completed') {
			$progressClassHotel = 'grayed';
			$progressMessageHotel = '<br /><h10 style="color:#279161">Completed!</h10>';
			$progressUrlHotel = '/hotel-registration';						
		} else if ($payment4EventId[91] === 'Incomplete') {
			$progressMessageHotel = '<br /><h10 style="color:#b40000">Incomplete</h10>';
			$progressUrlHotel = '/my-events';
		} else if ($payment4EventId[91] === 'Payment Declined') {
			$progressMessageHotel = '<br /><h10 style="color:#b40000">Incomplete</h10>';
			$progressUrlHotel = '/my-events';
		} else if ($payment4EventId[91] === 'Pending') {
			$progressMessageHotel = '<br /><h10 style="color:#FFA500">Pending</h10>';
			$progressUrlHotel = '/my-events';	
		} else {
			$progressUrlHotel = '/hotel-registration';
		}

		if ($payment4WorkshopId[95] === 'Completed' || $payment4WorkshopId[96] === 'Completed' || $payment4WorkshopId[97] === 'Completed' || $payment4WorkshopId[98] === 'Completed' || 
			$payment4WorkshopId[99] === 'Completed' || $payment4WorkshopId[100] === 'Completed' || $payment4WorkshopId[101] === 'Completed' || $payment4WorkshopId[102] === 'Completed') {			

			$progressClassWorkshops = 'grayed';		
			$progressMessageWorkshops = '<br /><h10 style="color:#279161">Completed!</h10>';
		}
		
		// Extra Options
		if ($payment4WorkshopId[120] === 'Completed') {
			$progressClassExtra = 'grayed';
			$progressMessageExtra = '<br /><h10 style="color:#279161">Completed!</h10>';
		}

	if (is_page(array('profile','login','create-profile','event-registration'))) {
		$progressClassProfile = 'current';
	}
	if (is_page('event-registration')) {
		$progressClassConference = 'current';
	}
	if (is_page('hotel-registration')) {
		$progressClassHotel = 'current';
	}
	if (is_page('workshop-registration')) {
		$progressClassWorkshops = 'current';
	}
	if (is_page('extra-options')) {
		$progressClassExtra = 'current';
	}
	
	$event_hashtag = do_shortcode('[EE_META type="event_meta" name="event_hashtag"]');
	
	if (is_page('Event Registration Form') && $event_hashtag === 'conference') {
		     $progressClassConference = 'current';
		
	} else if (is_page('Event Registration Form') && $event_hashtag === 'hotel') {
		     $progressClassHotel = 'current';
	}
			
		$newProgressBar = '<h10 class="progressHeader" style="font:11px Arial, sans-serif;color:#bfbfbf;padding:6px 0 12px 0;">Conference Registration Progress (Click on any of the steps)</h10>
			<table style="border:none;background:none;padding:10px 0 12px;">
				<tr style="">
				<td class="progressNumber '.$progressClassProfile.'"><a href="'.$progressUrlProfile.'"><h10 class="hide">Step</h10><br /></h10>1</a></td>
				<td class="nonNumber '.$progressClassProfile.'"><a href="'.$progressUrlProfile.'">Create Profile'.$progessMessageProfile.'</a></td>     
				<td class="progressNumber '.$progressClassConference.'"><a href="'.$progressUrlConference.'"><h10 class="hide">Step</h10><br /></h10>2</a></td>
				<td class="nonNumber '.$progressClassConference.'"><a href="'.$progressUrlConference.'">Register for Conference'.$progessMessageConference.'</a></td>
				<td class="progressNumber '.$progressClassHotel.'"><a href="'.$progressUrlHotel.'"><h10 class="hide">Step</h10><br /></h10>3</a></td>
				<td class="nonNumber '.$progressClassHotel.'"><a href="'.$progressUrlHotel.'">Register for Hotel'.$progressMessageHotel.'</a></td>
				<td class="progressNumber '.$progressClassWorkshops.'"><a href="/workshop-registration"><h10 class="hide">Step</h10><br /></h10>4</a></td>
				<td class="nonNumber '.$progressClassWorkshops.'"><a href="/workshop-registration">Register for Workshops'.$progressMessageWorkshops.'</a></td>
				<td class="progressNumber '.$progressClassExtra.'"><a href="/extra-options"><h10 class="hide">Step</h10><br /></h10>5</a></td>
				<td class="nonNumber '.$progressClassExtra.'"><a href="/extra-options">Extra Options'.$progressMessageExtra.'</a></td>
				</tr>
			</table>';
	}
	
// End warning messages

	}	
	$progress_with_title = $newProgressBar;
	echo $progress_with_title;
}
?>
