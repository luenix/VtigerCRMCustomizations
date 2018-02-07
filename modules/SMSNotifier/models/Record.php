<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/
vimport('~~/modules/SMSNotifier/SMSNotifier.php');

class SMSNotifier_Record_Model extends Vtiger_Record_Model {

	public static function SendSMS($message, $toNumbers, $currentUserId, $recordIds, $moduleName) {
		SMSNotifier::sendsms($message, $toNumbers, $currentUserId, $recordIds, $moduleName);
	}

	public function checkStatus() {
		$statusUpdate = SMSNotifier::smsquery($this->get('id'));
		$statusDetails = SMSNotifier::getSMSStatusInfo($this->get('id'));
		$messageDetails = SMSNotifier::getSMSInfo($this->get('id'));
		$message = $messageDetails[0]['message'];
		$i=0;
		while ($stat=$statusDetails[$i]['status']) {
			$statusColor = $this->getColorForStatus($stat);
			$statusDetails[$i]['statuscolor']=$statusColor;
			$statusDetails[$i]['message']=$message;
			$i++;
		}
		
		/* $this->setData($statusDetails); */
		/* added below line per community recommendation */
		$data = array_merge($statusDetails[0], array('statuscolor' => $statusColor));
		$this->setData($data);

		return $statusDetails;
	}

	public function getCheckStatusUrl() {
		return "index.php?module=".$this->getModuleName()."&view=CheckStatus&record=".$this->getId();
	}

	public function getColorForStatus($smsStatus) {
		if ($smsStatus == 'Processing') {
			$statusColor = '#FFFF80';
		} elseif ($smsStatus == 'Dispatched') {
			$statusColor = '#80FF80';
		} elseif ($smsStatus == 'Failed') {
			$statusColor = '#FF8080';
		} else {
			$statusColor = '#FFFFFF';
		}
		return $statusColor;
	}
}