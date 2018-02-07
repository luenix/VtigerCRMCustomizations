<?php

if(!function_exists("wf_send_to_xo")) {
    function wf_send_to_xo($trigger_type, $webhook_id, $lead_id, $email) {

	$response = file_get_contents('http://kanantik.the-xo.com/drip_campaigns/index.php?auth_token=jske372hsjejKlmmNnn827&webhook_id='.urlencode($webhook_id).'&lead_id='.urlencode($lead_id).'&trigger_type='.urlencode($trigger_type).'&email='.urlencode($email).'');
	return $response;	

	}
}
