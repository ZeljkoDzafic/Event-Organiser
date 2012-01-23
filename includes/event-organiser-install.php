<?php
 function eventorganiser_install(){
       global $wpdb, $eventorganiser_db_version, $eventorganiser_venue_table, $eventorganiser_events_table;
	$table_posts = $wpdb->prefix . "posts";

	$charset_collate = '';
	if ( ! empty($wpdb->charset) )
		$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
	if ( ! empty($wpdb->collate) )
		$charset_collate .= " COLLATE $wpdb->collate";

	//Events table
	$sql_events_table = "CREATE TABLE " .$eventorganiser_events_table. " (
		event_id bigint(20) NOT NULL AUTO_INCREMENT,
		post_id bigint(20) NOT NULL,
		Venue bigint(20) NOT NULL,
		StartDate DATE NOT NULL,
		EndDate DATE NOT NULL,
		StartTime TIME NOT NULL,
		FinishTime TIME NOT NULL,
		event_schedule text NOT NULL,
		event_schedule_meta text NOT NULL,
		event_frequency smallint NOT NULL,
		event_occurrence bigint(20) NOT NULL,
		event_allday TINYINT(1) NOT NULL,
		reoccurrence_start DATE NOT NULL,
		reoccurrence_end DATE NOT NULL,
		PRIMARY KEY  (event_id))".$charset_collate;
	
	//Venue table
	$sql_venue_table = "CREATE TABLE " . $eventorganiser_venue_table. " (
	  venue_id bigint(20) NOT NULL AUTO_INCREMENT,
	  venue_name text NOT NULL,
	  venue_slug text NOT NULL,
	  venue_address text NOT NULL,
	  venue_postal text NOT NULL,
	  venue_country text NOT NULL,
	  venue_lng FLOAT( 10, 6 ) NOT NULL DEFAULT 0,
	  venue_lat FLOAT( 10, 6 ) NOT NULL DEFAULT 0,
	  venue_owner bigint(20) NOT NULL,
	  venue_description longtext NOT NULL,
	  PRIMARY KEY  (venue_id) )".$charset_collate;

   require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
   dbDelta($sql_events_table);
   dbDelta($sql_venue_table);
	

	//Add options and capabilities
	$eventorganiser_options = array (	
		'supports' => array('title','editor','author','thumbnail','excerpt','custom-fields','comments'),
		'event_redirect' => 'events',
		'dateformat'=>'dd-mm',
		'prettyurl'=> 1,
		'templates'=> 1,
		'addtomenu'=> 0,
		'excludefromsearch'=>0,
		'showpast'=> 0,
		'url_venue'=>'events/event',
		'url_venue'=> 'events/venues',
		'url_cat' => 'events/category',
		'url_tag' => 'events/tag',
		'navtitle' => 'Events',
		'feed' => 1,
		'runningisnotpast' => 0,
		'deleteexpired' => 0
	);
	update_option("eventorganiser_version",$eventorganiser_db_version);
	add_option('eventorganiser_options',$eventorganiser_options);
	
	//Add roles to administrator		
	global $wp_roles,$eventorganiser_roles;	
	$all_roles = $wp_roles->roles;
	foreach ($all_roles as $role_name => $display_name):
		$role = $wp_roles->get_role($role_name);
		if($role->has_cap('manage_options')){
			foreach($eventorganiser_roles as $eo_role=>$eo_role_display):
				$role->add_cap($eo_role);
			endforeach;  
		}
	endforeach;  //End foreach $all_roles
}


function eventorganiser_deactivate(){
	eventorganiser_clear_cron_jobs();
    }


add_action('admin_init', 'eventorganiser_upgradecheck');
function eventorganiser_upgradecheck(){
       global $eventorganiser_db_version, $eventorganiser_events_table, $wpdb;
	global $EO_Errors;
	
	$installed_ver = get_option('eventorganiser_version');

	//If this is an old version, perform some updates.
	if ( !empty($installed_ver ) && $installed_ver != $eventorganiser_db_version ):
		  if ( $installed_ver < '1.1') {
			$query = $wpdb->prepare("SELECT* 
				FROM {$eventorganiser_events_table}
				WHERE {$eventorganiser_events_table}.event_schedule = 'monthly'
				GROUP BY {$eventorganiser_events_table}.post_id");
		
			$results = $wpdb->get_results($query); 
		
			foreach ( $results as $event ):
				$meta = $event->event_schedule_meta;
				$start = new DateTime(esc_attr($event->StartDate));
				$post_id = $event->post_id;

				$bymonthday =preg_match('/^BYMONTHDAY=(\d{1,2})/' ,$meta,$matches);
				$byday = preg_match('/^BYDAY=(-?\d{1,2})([a-zA-Z]{2})/' ,$meta,$matchesOLD);
				
				if(!($bymonthday || $byday )):

					if($meta=='date'):
						$meta = 'BYMONTHDAY='.$start->format('d');
					else:
						$meta = 'BYDAY='.$meta;
					endif;
					
					$result = $wpdb->update(
						$eventorganiser_events_table, 
						array('event_schedule_meta'=>$meta), 
						array('post_id'=>$post_id)
					); 
				endif;
			  endforeach;
		}
		if ( $installed_ver < '1.2') {
			$settings = get_option('eventorganiser_options');
			
			//Add new settings
			$settings['url_event']= 'events/event';
			$settings['url_venue']= 'events/venues';
			$settings['url_cat'] = 'events/category';
			$settings['url_tag'] = 'events/tag';
			$settings['navtitle'] = 'Events';
			$settings['navtitle'] = 'Events';
			$settings['feed'] = 1;
			$settings['deleteexpired'] = 0;
			update_option('eventorganiser_options',$settings);
		}
		update_option('eventorganiser_version', $eventorganiser_db_version);
	endif;
}


function eventorganiser_uninstall(){
	global $wpdb,$eventorganiser_venue_table, $eventorganiser_events_table,$eventorganiser_roles, $wp_roles,$wp_taxonomies;

	eventorganiser_clear_cron_jobs();

	//Drop tables    
	$wpdb->query("DROP TABLE IF EXISTS $eventorganiser_events_table");
	$wpdb->query("DROP TABLE IF EXISTS $eventorganiser_venue_table");

	//Remove all posts of CPT Event
	//?? $wpdb->query("DELETE FROM $wpdb->posts WHERE post_type = 'event'");

	//Delete options
	delete_option('eventorganiser_options');
	delete_option('eventorganiser_version');
	delete_option('eo_notice');
	delete_option('widget_eo_calendar_widget');
	delete_option('widget_eo_list_widget');

	//Remove Event Organiser capabilities
	$all_roles = $wp_roles->roles;
	foreach ($all_roles as $role_name => $display_name):
		$role = $wp_roles->get_role($role_name);
		foreach($eventorganiser_roles as $eo_role=>$eo_role_display):
			$role->remove_cap($eo_role);
		endforeach;  
	endforeach; 
	
	eventorganiser_clear_cron_jobs();

	//Remove 	event category and terms
	$terms = get_terms( 'event-category', 'hide_empty=0' );
		foreach ($terms as $term) {
			wp_delete_term( $term->term_id, 'event-category');
		}
		unset($wp_taxonomies['event-category']);

	//Remove user-meta-data:
	$meta_keys = array('metaboxhidden_event','closedpostboxes_event','wp_event_page_venues_per_page','manageedit-eventcolumnshidden');	
	$sql =$wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE ");
	foreach($meta_keys as $key):
		$sql .= $wpdb->prepare("meta_key = %s OR ",$key);
	endforeach;
	$sql.=" 1=0 "; //Deal with final 'OR', must be something false!
	$re =$wpdb->get_results( $sql);	

    }
?>