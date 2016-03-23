<?php

/**
 * Implements hook_form_FORMID_alter().
 * Just inject a placeholder text inside of our theme's search bar.
 */
function mysite_search_form_search_block_form_alter(&$form, &$form_state) {
    // HTML5 placeholder attribute
    $form['search_block_form']['#attributes']['placeholder'] = t('What are you looking for?');
}

/**
 * Implements hook_form_FORM_ID_alter(). 
 * This is for the search page - the Advanced Search form on the page. we want a 
 * image just to the right of the "Only of the type(s)." 
 * We want to pass in our image so we can slap it in the Advanced Search fieldset. http://mysite/search
 * Editing the image on the other end in the style.css file
 *
 */
function mysite_search_form_search_form_alter(&$form, &$form_state) {
  // For readability - grabbing a reference of the advanced array in forms. 
  $advanced = &$form['advanced'];
    
  /** Prepatory work here - there is an issue. I can't fit a 3rd column in that field set on the advanced search form. So I am going to shrink the width of the first column.*/
  // Keywords column
  $keywords = &$advanced['keywords'];
  foreach ($keywords as $keyword => &$values) {
    // seems to always have a prefix and suffix entry at first for any of these arrays
    if ($keyword != "#prefix" && $keyword != '#suffix') {
      $values['#size'] = 25; // it was at 30, lets change to 20.
    }
  }
  
  // Creating our new column.
  // Creating a new entry and stuffing it with the appropriate stuff (image and markup).
  $advanced['sweetser_search_image'] = array(
      '#prefix' => '<div class="criterion" id="sweetser-search-image"><a id="sweetser-search-link" href="http://sharon/docs/SitePages/Home.aspx">',
      '#suffix' => "</a> </div>",
      '#type' => 'fieldset',
  );
  
  $form['#attached']['js'] = array (
    drupal_get_path('theme', 'mysite') . '/_js/search_advanced.js',
  );
}

/**
 * Implements hook_query_TAG_alter(). For search_node (search system on ../search/node/...) - effects search-result.tpl.php
 * Make sure we only return calendar items with a FROM date (starting time of the event) that is current or after current date. 
 * 
 * We need to do a join on the with the node table and the field_data_field_date table, that way we get a reference to the FROM and the TO fields
 * that are associated with Event Items
 * 
 */
function mysite_search_query_search_node_alter(QueryAlterableInterface &$query) {    
    $time = gmdate("Y-m-d\TH:i:s", time());
    
    $u_alias = $query->addJoin('LEFT','field_data_field_date', 'fd', 'n.nid = fd.entity_id'); 
    $db_or = db_or();
    $db_or->isNull("{$u_alias}.field_date_value2");
    $db_or->condition("{$u_alias}.field_date_value2", $time, '>=');
    $query->condition($db_or);
}  

/**
 * Implements hook_preprocess_search_result(). for search-result.tpl.php page.
 * 
 * We want to make sure we add more details in for the search-result.tpl.php page.
 * Here we will be making sure that we check the From and To of a content type that is
 * calendar item. 
 * 
 * Convert it, check what time zone we are in, and then save the new values in the variable to be grabbed 
 * on the search-result.tpl.php page.
 * 
 * @param type $variables
 */
function mysite_search_preprocess_search_result(&$variables) {
  // lets add new variables
  
  // Grab content type.
  $content_type = $variables['result']['type'];
  // Leave hook if the content type is not a Calendar Item.
  if ($content_type !== 'Calendar Item') { 
    return; 
  }
  // content type is a Calendar Item.
  // Grab from date.
  $from_date;
  // Grab to date.
  $to_date;
  // Grab time zone
  $db_timezone = $variables['result']['node']->field_date['und'][0]['timezone_db'];
  // Grab the time array.  
  $time_array = _mysite_get_time_stamp($variables['result']['node']->field_date['und'][0]['value'], $db_timezone);

 // store our array time into the variables array to grab it on the search-result.tpl.php cleanly.
  $variables['date_from_calendar_item'] = $time_array;

  // store location. So I can grab it on the search-result.tpl.php cleanly.
  $location = $variables['result']['node']->field_event_location['und'][0]['taxonomy_term']->name;
  $variables['location_of_event'] = $location;

  // store summary. So I can grab it on the search-result.tpl.php cleanly.
  $variables['summary'] = strip_tags($variables['result']['node']->body['und'][0]['safe_summary']); // remove the tags <p> carries over. 
}

/**
 * Utility function to get the appropriate time stamp of the Date that the calendar Item started.
 * 
 * @param type $time - the time in a "2016-05-04T12:30:00" format. This is how Drupal saves the date.
 * @param type $db_timezone - Grab the db timezone - which is always UTC for mysite's db, but we need it to be EST to reflect the time accurately.
 * @return type - an Associative Array that has everything we need about the passed date.
 */
function _mysite_get_time_stamp($time, $db_timezone) {
  $secs = strtotime($time);
  $total = date("Y-m-d\TH:i:s", $secs);
  $crude_date = explode("-", $total);
  // the last result still needs to be broken up.
  $last_entry = $crude_date[sizeof($crude_date) - 1];
  
  $crude_day = explode("T", $crude_date[2]); // grab the day
  
  $year = $crude_date[0]; // grab the year
  $month = $crude_date[1]; // grab the month
  $day = $crude_day[0]; // grab the day
  
  $crude_time = explode(":", $crude_day[1]);
  
  $suffix = "AM"; // set the suffix
  
  $hour = $crude_time[0];
  // Change the time stamp to EST
  if ($db_timezone === 'UTC') {
    $hour = intval(str_replace('"', "", $hour)) - 4; // remove 2 hours.
  }
  
  // make sure we change the suffix if the the time is greater than 12.
  if ($hour >= 12) {
    $suffix = "PM";
  }
  
  // let's remove 0's from months that do not need them. 1-9
  $month = intval(str_replace('"', "", $month));
  
  
  $array_time = array(
    'year' => $year,
    'month' => $month, 
    'day' => $day,    
    'hour' => $hour,
    'minute' =>$crude_time[1],
    'second' => $crude_time[2],
    'suffix' => $suffix
  );
    // take whole / 60 to get minutes
       
  return $array_time;
}
