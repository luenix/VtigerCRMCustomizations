<?php

if(!function_exists("send_to_dialer")) {
    function send_to_dialer($fname, $lname, $email, $phone, $lead_no, $listid, $hearabout, $leadvendor, $srmleadid) {
        
//if hearabout is empty, use leadvendor
//  if ($hearabout == ''){
//    $hearabout = $entity->get('cf_957');
//  }
//if listid is 1010, hearabout and leadvendor set to XO
    if ($listid == 1010){
      $hearabout = XO;
      $leadvendor = XO;

    }

    $response = file_get_contents('http://dials.discoverbelize.com/vicidial/non_agent_api.php?source=test&user=5554&pass=Fuck0ffY0uWanker&function=add_lead&phone_code=1&list_id=1011&dnc_check=Y&duplicate_check=DUPSYS&custom_fields=Y&leadsource=XO&heardabout=XO&phone_number=7143135597&first_name=test&last_name=lead&vendor_lead_code=vendorcode&email=test@email.com&srmleadid=leadid');

    return $response; 
  }
}
