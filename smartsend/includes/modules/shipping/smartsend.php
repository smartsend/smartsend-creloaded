<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  class smartsend {
    var $code, $title, $description, $icon, $enabled;

// class constructor
    function smartsend() {
      global $order;

      $this->code = 'smartsend';
      $this->title = MODULE_SHIPPING_SMARTSEND_TEXT_TITLE;
      $this->description = MODULE_SHIPPING_SMARTSEND_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_SHIPPING_SMARTSEND_SORT_ORDER;
      $this->icon = '';
      
      $this->enabled = ((MODULE_SHIPPING_SMARTSEND_STATUS == 'True') ? true : false);


    }

// class methods
    function quote($method = '') {
             global $order,$cart, $shipping_weight, $shipping_num_boxes, $total_weight, $currencies,$db;
            $this->quotes = array();

            $topostcode     = str_replace(" ","",($order->delivery['postcode']));
            $tocountrycode  = $order->delivery['country']['iso_code_2'];
            $tosuburb       = $order->delivery['suburb'];
            $sweight        = $shipping_weight;

            if($tosuburb == ''){
                $tosuburb       = $order->delivery['city'];
            }

            $post_url = "http://api.smartsend.com.au/";


            # POST PARAMETER VALUES

            $post_param_values["METHOD"]                = "GetQuote";
            $post_param_values["FROMCOUNTRYCODE"]       = "AU";
            $post_param_values["FROMPOSTCODE"]          = "2000";
            $post_param_values["FROMSUBURB"]            = "SYDNEY";
            $post_param_values["TOCOUNTRYCODE"]         = $tocountrycode;
            $post_param_values["TOPOSTCODE"]            = $topostcode;
            $post_param_values["TOSUBURB"]              = $tosuburb;
            $post_param_values["RECEIPTEDDELIVERY"]     = MODULE_SHIPPING_SMARTSEND_RECEIPTEDDELIVERY;
            $post_param_values["TRANSPORTASSURANCE"]    = $order->info["total"];
            
            

            # tail lift - init    
            $taillift = array();
            $has_valid_products_info = true;
			
            # POST ITEMS VALUE
            foreach($order->products as $key => $data){
                $i = intval($data['id']);

                $products = tep_db_query("SELECT depth,length,height,description,taillift FROM smartsend_products WHERE id={$i}");    
                                
                $products = mysql_fetch_assoc($products);
                    
                 if(!$products) $has_valid_products_info = false; //return null or false;
				                                                  
                $post_value_items["ITEM({$key})_HEIGHT"]         =  $products['height'];
                $post_value_items["ITEM({$key})_LENGTH"]         =  $products['length'];
                $post_value_items["ITEM({$key})_DEPTH"]          =  $products['depth'];
                $post_value_items["ITEM({$key})_WEIGHT"]         =  $data['weight'];
                $post_value_items["ITEM({$key})_DESCRIPTION"]    =  $products['description'];
                
                
                    # tail lift - assigns value
                    switch($products['taillift']){
                        case 'none':
                            $taillift[] = "none";break;
                        case 'atpickup':
                            $taillift[] = "atpickup";break;    
                        case 'atdestination':
                            $taillift[] = "atdestination";break;                                                         
                        case 'both':
                            $taillift[] = "both";break;                                                         
                    }
            }
            
			# check if has valid product information 
			if(!$has_valid_products_info) return false;
			
            # tail lift - choose appropriate value
            $post_param_values["TAILLIFT"] = "none";            
            if (in_array("none",  $taillift))                                               $post_param_values["TAILLIFT"]      = "none";           
            if (in_array("atpickup",  $taillift))                                           $post_param_values["TAILLIFT"]      = "atpickup";
            if (in_array("atdestination",  $taillift))                                      $post_param_values["TAILLIFT"]      = "atdestination";
            if (in_array("atpickup",  $taillift) && in_array("atdestination",  $taillift))  $post_param_values["TAILLIFT"]      = "both";
            if (in_array("both",  $taillift))                                               $post_param_values["TAILLIFT"]      = "both";                              
            
            
            $post_final_values = array_merge($post_param_values,$post_value_items);

            # POST PARAMETER AND ITEMS VALUE URLENCODE
            $post_string = "";
            foreach( $post_final_values as $key => $value )
                    { $post_string .= "$key=" . urlencode( $value ) . "&"; }
            $post_string = rtrim( $post_string, "& " );

            
            # START CURL PROCESS
            $request = curl_init($post_url); 
            curl_setopt($request, CURLOPT_HEADER, 0); 
            curl_setopt($request, CURLOPT_RETURNTRANSFER, 1); 
            curl_setopt($request, CURLOPT_POSTFIELDS, $post_string);
            curl_setopt($request, CURLOPT_SSL_VERIFYPEER, FALSE);
            $post_response = curl_exec($request); 
            curl_close ($request); // close curl object    
            
            

            # parse output
            parse_str($post_response, $arr_resp);

            $quote_count = ((int) $arr_resp["QUOTECOUNT"]) - 1;

            # JAVASCRIPT MANIPULATION
            $script='<script src="http://ontech.redber.net/zencart-mark/smartsend.js"></script>';

            # Initialise our arrays
            $this->quotes = array('id' => $this->code, 'module' => $this->title);
            $methods = array() ;

            # ASSIGNING VALUES TO ARRAY METHODS    
            for ($x=0; $x<=$quote_count; $x++)
            {
              $methods[] = array( 'id' => "quote{$x}",  'title' => "{$arr_resp["QUOTE({$x})_SERVICE"]}"." <label>{$arr_resp["QUOTE({$x})_ESTIMATEDTRANSITTIME"]}</label>".$script,'cost' => $arr_resp["QUOTE({$x})_TOTAL"] ) ;      
            }

            $sarray[]   = array(); 
            $resultarr  = array() ;

            foreach($methods as $key => $value) {
                    $sarray[ $key ] = $value['cost'] ;
            }

            asort( $sarray ) ;

            foreach($sarray as $key => $value) {
                    $resultarr[ $key ] = $methods[ $key ] ;
            }

            # ASSIGN QUOTES OF METHOD ARRAY VALUES
            $this->quotes['methods'] = array_values($resultarr) ;   // set it

            # SORT THE CHEAPEST
            if ($method) {

                foreach($methods as $temp) {
                 $search = array_search("$method", $temp) ;
                 if (strlen($search) > 0 && $search >= 0) {
                     break;
                    }
                  } ;

                $this->quotes = array('id' => $this->code, 'module' => $this->title,'methods' => array( array('id' => $method,'title' => $temp['title'],'cost' => $temp['cost'] )));
            }    

            if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);            

            return $this->quotes;  

    }

    function check() {
        if (!isset($this->_check)) {
          $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_SMARTSEND_STATUS'");
          $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    function install() {
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
        values ('Enable Smart Send', 'MODULE_SHIPPING_SMARTSEND_STATUS', 'True', 'Do you want to offer Smart Send plugin?', '66', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
  
   
    # USERCODE
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('USER CODE', 'MODULE_SHIPPING_SMARTSEND_USERCODE', '', 
        '(Optional) The code corresponding to the USERTYPE value. ', 
        '66', '0', now())");

    # USERTYPE
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('USER TYPE', 'MODULE_SHIPPING_SMARTSEND_USERTYPE', '',
        '(Optional) The user type making the quote request. Used in conjunction with USERCODE if appropriate. Valid values are , ebay, corporate, promotion.', 
        '66', '0', now())");

    /*
    # TRANSPORTASSURANCE
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('TRANSPORT ASSURANCE', 'MODULE_SHIPPING_SMARTSEND_TRANSPORTASSURANCE', '0.00', 
        '(Optional) The wholesale value of the goods, specified in AUS $ for the purposes of transport assurance cover. Maximum value 10000.00', 
        '66', '0', now())");
    
    # TAILLIFT
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,use_function, set_function,  date_added) 
        values ('TAIL LIFT', 'MODULE_SHIPPING_SMARTSEND_TAILLIFT', '0', 
        '(Optional) Specifies whether a tail lift service is required. Acceptable values are None, AtPickup, AtDestination, Both', 
        '66', '0', 'tep_get_tail_class_title', 'tep_cfg_pull_down_tail_classes(',  now())");
    
     * 
     */
    
    # RECEIPTEDDELIVERY
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,use_function, set_function,  date_added) 
        values ('RECEIPTED DELIVERY', 'MODULE_SHIPPING_SMARTSEND_RECEIPTEDDELIVERY', '0', 
        '(Optional) Yes / No  that specifies whether or not recipient is required to sign for the consignment', 
        '66', '0', 'tep_get_rdelivery_class_title', 'tep_cfg_pull_down_rdelivery_classes(',  now())");
    

    # SERVICE TYPE
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,use_function, set_function,  date_added) 
        values ('SERVICE TYPE', 'MODULE_SHIPPING_SMARTSEND_SERVICETYPE', '0', 
        '(Optional)', 
        '66', '0', 'tep_get_service_class_title', 'tep_cfg_pull_down_service_classes(',  now())");
    
    
    # COUNTRY CODE
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('COUNTRY CODE', 'MODULE_SHIPPING_SMARTSEND_COUNTRYCODE', 'AU', 
        '(Optional) The 2 letter country code (ISO-3166) where the consignment will be picked up (Default AU).', 
        '66', '0', now())");

    # POSTCODE
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('POST CODE', 'MODULE_SHIPPING_SMARTSEND_POSTCODE', '', 
        '<span style=\'color:red\'>(Required)</span> The post code where the consignment will be picked up. ', 
        '66', '0', now())");

    # SUBURBAN
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('SUBURBAN', 'MODULE_SHIPPING_SMARTSEND_SUBURB', 'sydney', 
        '<span style=\'color:red\'>(Required)</span> The suburb/city where the consignment will be picked up. Must be valid when combined with FROMPOSTCODE otherwise an error will be returned. ', 
        '66', '0', now())");

    # CONTACT COMPANY
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('CONTACT COMPANY', 'MODULE_SHIPPING_SMARTSEND_CONTACTCOMPANY', '', 
        '(Optional) The contact company responsible for the booking. ', 
        '66', '0', now())");

    # CONTACT NAME    
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('CONTACT NAME', 'MODULE_SHIPPING_SMARTSEND_CONTACTNAME', '', 
        '(Optional) The name of the contact person responsible for the booking. ', 
        '66', '0', now())");
    
    # CONTACT PHONE 
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('CONTACT PHONE', 'MODULE_SHIPPING_SMARTSEND_CONTACTPHONE', '', 
        '<span style=\'color:red\'>(Required)</span> Contact phone of the person responsible for the booking (10 digits - area code included); critical for verification purposes. ', 
        '66', '0', now())");
    
    # CONTACT EMAIL    
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('CONTACT EMAIL', 'MODULE_SHIPPING_SMARTSEND_CONTACTEMAIL', '', 
        '<span style=\'color:red\'>(Required)</span> The email address of the person to be contacted regarding the booking if required; critical for verification purposes. ', 
        '66', '0', now())");
    
    # PICKUP COMPANY
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP COMPANY', 'MODULE_SHIPPING_SMARTSEND_PICKUPCOMPANY', '', 
        '(Optional) Name of the company at the pickup location. ', 
        '66', '0', now())");
    
    # PICKUP CONTACT     
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP CONTACT', 'MODULE_SHIPPING_SMARTSEND_PICKUPCONTACT', '', 
        '<span style=\'color:red\'>(Required)</span> Name of the contact person at the pickup location. ', 
        '66', '0', now())");
    
    # PICKUP ADDRESS1
     tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP ADDRESS1', 'MODULE_SHIPPING_SMARTSEND_PICKUPADDRESS1', '', 
        '<span style=\'color:red\'>(Required)</span>  Address line 1 of the pickup location. ', 
        '66', '0', now())");
     
    # PICKUP ADDRESS2     
     tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP ADDRESS2', 'MODULE_SHIPPING_SMARTSEND_PICKUPADDRESS2', '', 
        '(Optional) Address line 2 of the pickup location. ', 
        '66', '0', now())");
     
    # PICKUP PHONE          
     tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP PHONE', 'MODULE_SHIPPING_SMARTSEND_PICKUPPHONE', '', 
        '<span style=\'color:red\'>(Required)</span> Contact phone of the person at the pickup location (10 digits - area code included). ', 
        '66', '0', now())");

         
    # PICKUP SUBURB
     tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP SUBURB', 'MODULE_SHIPPING_SMARTSEND_PICKUPSUBURB', '', 
        '<span style=\'color:red\'>(Required)</span> Suburb of the pickup location. ', 
        '66', '0', now())");
    
    # PICKUP POSTCODE
     tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP POSTCODE', 'MODULE_SHIPPING_SMARTSEND_PICKUPPOSTCODE', '', 
        '<span style=\'color:red\'>(Required)</span> Post code of the pickup location. ', 
        '66', '0', now())");
     
    # PICKUP STATE     
     tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP STATE', 'MODULE_SHIPPING_SMARTSEND_PICKUPSTATE', '', 
        '<span style=\'color:red\'>(Required)</span> State of the pickup location (use abbreviation e.g. NSW) ', 
        '66', '0', now())");

/*
    # PICKUP DATE   
     $desc_date = mysql_real_escape_string('<span style=\'color:red\'>(Required)</span> Sets the pickup date. (format dd/mm/yyyy. e.g. 25/07/2010)');    
     tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
        values ('PICKUP DATE', 'MODULE_SHIPPING_SMARTSEND_PICKUPDATE', '', 
        '{$desc_date}', 
        '66', '0', now())");
     
    # PICKUP TIME
    tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,use_function, set_function,  date_added) 
        values ('PICKUPTIME', 'MODULE_SHIPPING_SMARTSEND_PICKUPTIME', '0', 
        '<span style=\'color:red\'>(Required)</span> Sets the pickup time window. Valid values are 1 (between 12pm and 4pm) and 2 (between 1pm and 5pm). ', 
        '66', '0', 'tep_get_picktime_class_title', 'tep_cfg_pull_down_picktime_classes(',  now())");
    
  */  
    
    
  
        $tables = tep_db_query("SHOW TABLES like 'smartsend_products'");    
        if (tep_db_num_rows($tables) <= 0) {
            tep_db_query(" 
            CREATE TABLE IF NOT EXISTS `smartsend_products` (
              `description` varchar(20) NOT NULL,
              `id` int(11) NOT NULL,
              `depth` int(11) NOT NULL,
              `length` int(11) NOT NULL,
              `height` int(11) NOT NULL,
              `taillift` varchar(20) NOT NULL,
              KEY `id` (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
        }
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key LIKE '%smartsend%'");
    }

    function keys() {
    return array(
        'MODULE_SHIPPING_SMARTSEND_STATUS', 
        'MODULE_SHIPPING_SMARTSEND_USERCODE',
        'MODULE_SHIPPING_SMARTSEND_USERTYPE',
        'MODULE_SHIPPING_SMARTSEND_RECEIPTEDDELIVERY',
        'MODULE_SHIPPING_SMARTSEND_COUNTRYCODE',
        'MODULE_SHIPPING_SMARTSEND_POSTCODE',
        'MODULE_SHIPPING_SMARTSEND_SUBURB',
        'MODULE_SHIPPING_SMARTSEND_CONTACTCOMPANY',
        'MODULE_SHIPPING_SMARTSEND_CONTACTNAME',
        'MODULE_SHIPPING_SMARTSEND_CONTACTPHONE',        
        'MODULE_SHIPPING_SMARTSEND_CONTACTEMAIL',
        'MODULE_SHIPPING_SMARTSEND_PICKUPCONTACT',
        'MODULE_SHIPPING_SMARTSEND_PICKUPCOMPANY',
        'MODULE_SHIPPING_SMARTSEND_PICKUPADDRESS1',
        'MODULE_SHIPPING_SMARTSEND_PICKUPADDRESS2',                    
        'MODULE_SHIPPING_SMARTSEND_PICKUPPHONE',
        'MODULE_SHIPPING_SMARTSEND_PICKUPSUBURB',
        'MODULE_SHIPPING_SMARTSEND_PICKUPPOSTCODE',
        'MODULE_SHIPPING_SMARTSEND_PICKUPSTATE');    

    /*
    return array(
        'MODULE_SHIPPING_SMARTSEND_STATUS', 
        'MODULE_SHIPPING_SMARTSEND_USERCODE',
        'MODULE_SHIPPING_SMARTSEND_USERTYPE',
        'MODULE_SHIPPING_SMARTSEND_TRANSPORTASSURANCE',
        'MODULE_SHIPPING_SMARTSEND_TAILLIFT',
        'MODULE_SHIPPING_SMARTSEND_RECEIPTEDDELIVERY',
        'MODULE_SHIPPING_SMARTSEND_COUNTRYCODE',
        'MODULE_SHIPPING_SMARTSEND_POSTCODE',
        'MODULE_SHIPPING_SMARTSEND_SUBURB',
        'MODULE_SHIPPING_SMARTSEND_CONTACTCOMPANY',
        'MODULE_SHIPPING_SMARTSEND_CONTACTNAME',
        'MODULE_SHIPPING_SMARTSEND_CONTACTPHONE',        
        'MODULE_SHIPPING_SMARTSEND_CONTACTEMAIL',
        'MODULE_SHIPPING_SMARTSEND_PICKUPCONTACT',
        'MODULE_SHIPPING_SMARTSEND_PICKUPCOMPANY',
        'MODULE_SHIPPING_SMARTSEND_PICKUPADDRESS1',
        'MODULE_SHIPPING_SMARTSEND_PICKUPADDRESS2',                    
        'MODULE_SHIPPING_SMARTSEND_PICKUPPHONE',
        'MODULE_SHIPPING_SMARTSEND_PICKUPSUBURB',
        'MODULE_SHIPPING_SMARTSEND_PICKUPPOSTCODE',
        'MODULE_SHIPPING_SMARTSEND_PICKUPSTATE',
        'MODULE_SHIPPING_SMARTSEND_PICKUPDATE',
        'MODULE_SHIPPING_SMARTSEND_PICKUPTIME');    
        
     */
    
    }
    
  }
  
  

/* ************************* ADDITIONAL FUNCTION **************************** */

/* Name  : TAIL LIFT
 * Desc  : set the tail lift value in admin
 * Found : 'admin->shipping module'
 * 
 * How to access the value : just call 'MODULE_SHIPPING_SMARTSEND_TAILLIFT'
 */

  # Set func TAIL LIFT
  function tep_cfg_pull_down_tail_classes($id, $key = '') {
    global $db;
    $name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');
    
    $taillift[] = Array ("id" => "none","text" => "NO");
    $taillift[] = Array ("id" =>  "atpickup" ,"text" => "Yes - At Pickup");
    $taillift[] = Array ("id" =>  "atdestination","text" => "Yes - At Delivery");
    $taillift[] = Array ("id" =>  "both","text" => "Yes - At Pickup and Delivery");
    
    return tep_draw_pull_down_menu($name, $taillift , $id);  
    
  }    
  
  # Use func TAIL LIFT
  function tep_get_tail_class_title($id) {
    global $db;    
    
    $taillift[] = Array ("id" => "none","text" => "NO");
    $taillift[] = Array ("id" =>  "atpickup" ,"text" => "Yes - At Pickup");
    $taillift[] = Array ("id" =>  "atdestination","text" => "Yes - At Delivery");
    $taillift[] = Array ("id" =>  "both","text" => "Yes - At Pickup and Delivery");
    
    foreach($taillift as $val){
        if($val["id"] == $id){
          return $val['text'];      
        }
    }
    
  }
  
  
/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ <new func> ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
  
  
  
/* Name  : SERVICE TYPE
 * Desc  : set the service type value in admin
 * Found : 'admin->shipping module'
 * 
 * How to access the value : just call 'MODULE_SHIPPING_SMARTSEND_SERVICETYPE'
 */
  # Set func SERVICE TYPE
  function tep_cfg_pull_down_service_classes($id, $key = '') {
    global $db;
    $name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');
    
    $service[] = Array ("id" =>  "ALL","text" => "ALL");
    $service[] = Array ("id" =>  "ROAD" ,"text" => "ROAD");
    $service[] = Array ("id" =>  "EXPRESS","text" => "EXPRESS");
    $service[] = Array ("id" =>  "ALLIEDEXPRESSROAD","text" => "ALLIEDEXPRESSROAD");
    $service[] = Array ("id" =>  "ALLIEDEXPRESSOVERNIGHT","text" => "ALLIEDEXPRESSOVERNIGHT");
    $service[] = Array ("id" =>  "MAINFREIGHTROAD" ,"text" => "MAINFREIGHTROAD");
    $service[] = Array ("id" =>  "TNTROAD","text" => "TNTROAD");
    $service[] = Array ("id" =>  "TNTOVERNIGHT","text" => "TNTOVERNIGHT");
    $service[] = Array ("id" =>  "TNTOVERNIGHTAM","text" => "TNTOVERNIGHTAM");
    $service[] = Array ("id" =>  "TNTNEXTFLIGHT" ,"text" => "TNTNEXTFLIGHT");
    $service[] = Array ("id" =>  "DHLROAD","text" => "DHLROAD");
    $service[] = Array ("id" =>  "DHLEXPRESS","text" => "DHLEXPRESS");
    $service[] = Array ("id" =>  "AAEEXPRESSECONOMY","text" => "AAEEXPRESSECONOMY");
    $service[] = Array ("id" =>  "AAEEXPRESSPREMIUM","text" => "AAEEXPRESSPREMIUM");
    

    
    return tep_draw_pull_down_menu($name, $service , $id);  
    
  }    
  
  # Use func SERVICE TYPE
  function tep_get_service_class_title($id) {
    global $db;    
    
    $service[] = Array ("id" => "ALL","text" => "ALL");
    $service[] = Array ("id" =>  "ROAD" ,"text" => "ROAD");
    $service[] = Array ("id" =>  "EXPRESS","text" => "EXPRESS");
    $service[] = Array ("id" =>  "ALLIEDEXPRESSROAD","text" => "ALLIEDEXPRESSROAD");
    $service[] = Array ("id" => "ALLIEDEXPRESSOVERNIGHT","text" => "ALLIEDEXPRESSOVERNIGHT");
    $service[] = Array ("id" =>  "MAINFREIGHTROAD" ,"text" => "MAINFREIGHTROAD");
    $service[] = Array ("id" =>  "TNTROAD","text" => "TNTROAD");
    $service[] = Array ("id" =>  "TNTOVERNIGHT","text" => "TNTOVERNIGHT");
    $service[] = Array ("id" => "TNTOVERNIGHTAM","text" => "TNTOVERNIGHTAM");
    $service[] = Array ("id" =>  "TNTNEXTFLIGHT" ,"text" => "TNTNEXTFLIGHT");
    $service[] = Array ("id" =>  "DHLROAD","text" => "DHLROAD");
    $service[] = Array ("id" =>  "DHLEXPRESS","text" => "DHLEXPRESS");
    $service[] = Array ("id" =>  "AAEEXPRESSECONOMY","text" => "AAEEXPRESSECONOMY");
    $service[] = Array ("id" =>  "AAEEXPRESSPREMIUM","text" => "AAEEXPRESSPREMIUM");
    
    
    foreach($service as $val){
        if($val["id"] == $id){
          return $val['text'];      
        }
    }
    
  }  
  
/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ <new func> ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
  
/* Name  : PICKUP TIME
 * Desc  : set the PICKUP TIME value in admin
 * Found : 'admin->shipping module'
 * 
 * How to access the value : just call 'MODULE_SHIPPING_SMARTSEND_TAILLIFT'
 */

  # Set func PICKUP TIME
  function tep_cfg_pull_down_picktime_classes($id, $key = '') {
    global $db;
    $name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');
    

    $ptime[] = Array ("id" =>  "1" ,"text" => "between 12pm and 4pm");
    $ptime[] = Array ("id" =>  "2","text" => "between 1pm and 5pm");

    
    return tep_draw_pull_down_menu($name, $ptime , $id);  
    
  }    
  
  # Use func PICKUP TIME
  function tep_get_picktime_class_title($id) {
    global $db;    
    
    $ptime[] = Array ("id" =>  "1" ,"text" => "between 12pm and 4pm");
    $ptime[] = Array ("id" =>  "2","text" => "between 1pm and 5pm");

    
    foreach($ptime as $val){
        if($val["id"] == $id){
          return $val['text'];      
        }
    }
    
  }  
  
/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ <new func> ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
/* Name  : RECEIPTED DELIVERY
 * Desc  : set the PICKUP TIME value in admin
 * Found : 'admin->shipping module'
 * 
 * How to access the value : just call 'MODULE_SHIPPING_SMARTSEND_TAILLIFT'
 */

  # Set array RECEIPTED DELIVERY
  function tep_arr_rdelivery(){
    $ptime[] = Array ("id" =>  "true" ,"text" => "YES");
    $ptime[] = Array ("id" =>  "false","text" => "NO");
    return $ptime;
  }
  
  # Set func RECEIPTED DELIVERY
  function tep_cfg_pull_down_rdelivery_classes($id, $key = '') {
    $name = (($key) ? 'configuration[' . $key . ']' : 'configuration_value');
    return tep_draw_pull_down_menu($name, tep_arr_rdelivery() , $id);      
  }    
  
  # Use func RECEIPTED DELIVERY
  function tep_get_rdelivery_class_title($id) {
    $ptime = tep_arr_rdelivery();
    foreach($ptime as $val){
        if($val["id"] == $id){
          return $val['text'];      
        }
    }
    
  }  
  
  
?>
