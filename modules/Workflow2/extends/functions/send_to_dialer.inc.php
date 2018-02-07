<?php

if(!function_exists("wf_send_to_dialer")) {
  function wf_send_to_dialer($fname, $lname, $email, $phone, $lead_no, $listid, $hearabout, $leadvendor, $srmleadid) {
    $response = file_get_contents('http://dials.discoverbelize.com/vicidial/non_agent_api.php?source=test&user=-----&pass=-----&function=add_lead&phone_code=1&list_id='.urlencode($listid).'&dnc_check=Y&duplicate_check=DUPSYS&custom_fields=Y&leadsource='.urlencode($leadvendor).'&heardabout='.urlencode($hearabout).'&phone_number='.urlencode($phone).'&first_name='.urlencode($fname).'&last_name='.urlencode($lname).'&vendor_lead_code='.urlencode($lead_no).'&email='.urlencode($email).'&srmleadid='.urlencode($srmleadid).'');

    return $response;
  }
}
