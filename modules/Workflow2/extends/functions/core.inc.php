<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Stefan Warnat <support@stefanwarnat.de>
 * Date: 29.07.13
 * Time: 18:07
 */
if(!function_exists("wf_get_entity")) {
    function wf_get_entity($entity_id, $module_name = false) {
        $object = \Workflow\VTEntity::getForId($entity_id, $module_name);
        return $object->getData();
    }
}
if(!function_exists("wf_recordlist")) {
    function wf_recordlist($listId) {
        $context = \Workflow\ExpressionParser::$INSTANCE->getContext();
        $env = $context->getEnvironment($listId);
        $html = $env['html'];

        return $html;
    }
}

if(!function_exists("wf_json_encode")) {
    function wf_json_encode($value) {
        echo json_encode($value);
    }
}

if(!function_exists("wf_getcampaignstatus")) {
    function wf_getcampaignstatus($campaignId, $recordModule, $recordId) {
        if($recordModule == 'Leads') {
            $sql = 'SELECT data.campaignrelstatusid, campaignrelstatus FROM vtiger_campaignleadrel as data LEFT JOIN vtiger_campaignrelstatus ON (vtiger_campaignrelstatus.campaignrelstatusid = data.campaignrelstatusid) WHERE campaignid = ? AND leadid = ?';
        } elseif($recordModule == 'Contacts') {
            $sql = 'SELECT data.campaignrelstatusid, campaignrelstatus FROM vtiger_campaigncontrel as data LEFT JOIN vtiger_campaignrelstatus ON (vtiger_campaignrelstatus.campaignrelstatusid = data.campaignrelstatusid) WHERE campaignid = ? AND contactid = ?';
        } elseif($recordModule == 'Accounts') {
            $sql = 'SELECT data.campaignrelstatusid, campaignrelstatus FROM vtiger_campaignaccountrel as data LEFT JOIN vtiger_campaignrelstatus ON (vtiger_campaignrelstatus.campaignrelstatusid = data.campaignrelstatusid) WHERE campaignid = ? AND accountid = ?';
        } else {
            return 0;
        }

        $adb = \PearDatabase::getInstance();

        $result = $adb->pquery($sql, array(intval($campaignId), $recordId));
        if($adb->num_rows($result) > 0) {
            $data = $adb->fetchByAssoc($result);
            if($data['campaignrelstatusid'] == '1') {
                return '';
            } else {
                return $data['campaignrelstatus'];
            }
        }

        return 0;
    }
}

if(!function_exists("wf_fieldvalue")) {
    function wf_fieldvalue($crmid, $moduleName, $field) {
        $entity = \Workflow\VTEntity::getForId($crmid, $moduleName);

        if($entity === false) {
            throw new \Exception('You try to use wf_fieldvalue with a wrong crmid ('.$crmid.')');
        }

        return $entity->get($field);
    }
}

if(!function_exists("wf_date")) {
    function wf_date($value, $interval, $format = "Y-m-d") {
        if(empty($interval)) {
            $dateValue = strtotime($value);
        } else {
            $dateValue = strtotime($interval, strtotime($value));
        }

        return date($format, $dateValue);
    }
}

if(!function_exists("wf_salutation")) {
    function wf_salutation($value, $language = false) {
        global $adb, $current_language;

        if($language === false) {
            $language = $current_language;
        }

		require("modules/Contacts/language/".$language.".lang.php");
		return $mod_strings[$value];
    }
}

if(!function_exists("wf_log")) {
    function wf_log($value) {
        Workflow2::$currentBlockObj->addStat($value);
    }
}
if(!function_exists("wf_getenv")) {
    function wf_getenv($key) {
        \Workflow2::$currentWorkflowObj->getContext()->getEnvironment($key);
    }
}
if(!function_exists("wf_setenv")) {
    function wf_setenv($key, $value) {
        \Workflow2::$currentWorkflowObj->getContext()->setEnvironment($key, $value);
        var_dump(\Workflow2::$currentWorkflowObj->getContext()->getEnvironment());
    }
}

if(!function_exists("wf_setfield")) {
    function wf_setfield($field, $value) {
        VTWfExpressionParser::$INSTANCE->getContext()->set($field, $value);
    }
}

if(!function_exists("wf_save_record")) {
    function wf_save_record() {
        VTWfExpressionParser::$INSTANCE->getContext()->save();
    }
}
if(!function_exists("wf_recordurl")) {
    function wf_recordurl($crmid) {
        $crmid = intval($crmid);
        $objTMP = \Workflow\VTEntity::getForId($crmid);
        global $site_URL;
        return $site_URL.'/index.php?module='.$objTMP->getModuleName().'&view=Detail&record='.$crmid;

    }
}
if(!function_exists("wf_recordlink")) {
    function wf_recordlink($crmid, $text = '') {
        $url = wf_recordurl($crmid);

        return '<a href="'.$url.'">'.$text.'</a>';

    }
}
if(!function_exists("wf_dbquery")) {
    function wf_dbquery($query) {
        $adb = PearDatabase::getInstance();

        $result = $adb->query($query, false);
        $errorNo = $adb->database->ErrorNo();
        if(!empty($errorNo)) {
            Workflow2::error_handler(E_NONBREAK_ERROR, $adb->database->ErrorMsg());
        } else {
            if($adb->num_rows($result) > 0) {
                $row = $adb->fetchByAssoc($result);
                return $row;
            } else {
                return array();
            }
        }

        # need vtiger Database to reset Selected DB in the case the query changed this
        global $dbconfig;
        $adb->database->SelectDB($dbconfig['db_name']);
    }
}
if(!function_exists("wf_dbSelectAll")) {
    function wf_dbSelectAll($query) {
        $adb = PearDatabase::getInstance();

        $result = $adb->query($query, false);
        $errorNo = $adb->database->ErrorNo();
        if(!empty($errorNo)) {
            Workflow2::error_handler(E_NONBREAK_ERROR, $adb->database->ErrorMsg());
        } else {
            if($adb->num_rows($result) > 0) {
                $return = array();
                while($row = $adb->fetchByAssoc($result)) {
                    $return[] = $row;
                }
                return $return;
            } else {
                return array();
            }
        }

        # need vtiger Database to reset Selected DB in the case the query changed this
        global $dbconfig;
        $adb->database->SelectDB($dbconfig['db_name']);
    }
}
if(!function_exists("wf_formatcurrency")) {
    function wf_formatcurrency($value) {
        $currencyField = new CurrencyField($value);
        return $currencyField->getDisplayValue(null, true);
    }
}
if(!function_exists('wf_oldvalue')) {
    function wf_oldvalue($field, $crmid) {
        if(empty($crmid)) {
            return false;
        }

        $objRecord = \Workflow\VTEntity::getForId($crmid);

        return \Workflow\EntityDelta::getOldValue($objRecord->getModuleName(), $crmid, $field);
    }
}
if(!function_exists('wf_haschanged')) {
    function wf_haschanged($field, $crmid) {
        if(empty($crmid)) {
            return false;
        }

        $objRecord = \Workflow\VTEntity::getForId($crmid);

        return \Workflow\EntityDelta::hasChanged($objRecord->getModuleName(), $crmid, $field);
    }
}
if(!function_exists('wf_changedfields')) {
    function wf_changedfields($crmid, $internalFields = false) {
        if(empty($crmid)) {
            return false;
        }

        $objRecord = \Workflow\VTEntity::getForId($crmid);

        return \Workflow\EntityDelta::changeFields($objRecord->getModuleName(), $crmid, $internalFields);
    }
}
function wf_recordlabel($crmid) {
    if(empty($crmid)) {
        return false;
    }

    return \Vtiger_Functions::getCRMRecordLabel($crmid);
}
if(!function_exists('wf_fieldlabel')) {
    function wf_fieldlabel($module, $fieldName) {
        if(!is_array($fieldName)) {
            $fieldName = array($fieldName);
            $single = true;
        } else {
            $single = false;
        }
        $tabid = getTabid($module);

        foreach($fieldName as $field) {
            if($field == 'crmid') {
                $fieldLabel = 'CRMID';
            } else {
                $fieldInfo = \Workflow\VtUtils::getFieldInfo($field, $tabid);

                $fieldLabel = $fieldInfo['fieldlabel'];
            }
            if(empty($fieldLabel)) {
                $fieldLabel = $field;
            }

            $return[] = $fieldLabel;
        }

        if($single === true) {
            return $return[0];
        } else {
            return $return;
        }
    }
}

/*
if(!function_exists('wf_getproducts')) {
    function wf_getproducts($crmid, $combine = false) {
        if(strpos($crmid, ',') !== false) {
            $crmid = explode(',', $crmid);
        }
        if(!is_array($crmid)) {
            $crmid = array($crmid);
        }

        $return = array();
        foreach($crmid as $id) {
            $context = \Workflow\VTEntity::getForId($id);
            $products = getAssociatedProducts($context->getModuleName(), $context->getInternalObject());

            if($combine === false) {
                if(!isset($return[$id])) $return[$id] = array();

                foreach($products as $product) {
                    $return[$id]
                }
            }
        }
    }
}
*/

if(!function_exists('wf_requestvalues')) {
    function wf_requestvalues($fields, $label, $pausable = false, $stoppable = false) {
        $currentBlock = \Workflow2::$currentBlockObj;
        $currentWorkflow = \Workflow2::$currentWorkflowObj;

        $blockKey = 'block_'.$currentBlock->getBlockId();

        if(!$currentWorkflow->hasRequestValues($blockKey)) {

            $export = array('version' => \Workflow\Preset\FormGenerator::VERSION, 'fields' => $fields);

            $currentWorkflow->requestValues($blockKey, $export, $currentBlock, $label, $currentWorkflow->getContext(), $stoppable, $pausable);

            return false;
        }
    }
}

if(!function_exists("wf_combine_comments")) {
    function wf_combine_comments($crmid, $limit = null) {
        global $adb, $default_charset;

        $sql = "SELECT *
           FROM
               vtiger_modcomments
           INNER JOIN vtiger_crmentity
               ON (vtiger_crmentity.crmid = vtiger_modcomments.modcommentsid)
           INNER JOIN vtiger_users
               ON (vtiger_users.id = vtiger_crmentity.smownerid)
           WHERE related_to = ".$crmid." AND vtiger_crmentity.deleted = 0 ORDER BY createdtime DESC  ".(!empty($limit)?' LIMIT '.$limit:'')."";
        $result = $adb->query($sql, true);

        $html = "";
        while($row = $adb->fetchByAssoc($result)) {
            if(!empty($row['customer'])) {

            }
            $html .= "<div style='font-size:12px;'><strong>".(!empty($row['customer'])?Vtiger_Functions::getCRMRecordLabel($row['customer']):$row["first_name"]." ".$row["last_name"])." - ".date("d.m.Y H:i:s", strtotime($row["createdtime"]))."</strong><br>";
            $html .= nl2br($row["commentcontent"])."</div><br><br>";
        }

        return $html;
    }
}


if(!function_exists('wf_converttimezone')) {
    // by user2622929
    // http://stackoverflow.com/questions/3905193/convert-time-and-date-from-one-time-zone-to-another-in-php
    function wf_converttimezone($time, $currentTimezone, $timezoneRequired)
    {
        $dayLightFlag = false;
        $dayLgtSecCurrent = $dayLgtSecReq = 0;
        $system_timezone = date_default_timezone_get();
        $local_timezone = $currentTimezone;
        date_default_timezone_set($local_timezone);
        $local = date("Y-m-d H:i:s");
        /* Uncomment if daylight is required */
        //        $daylight_flag = date("I", strtotime($time));
        //        if ($daylight_flag == 1) {
        //            $dayLightFlag = true;
        //            $dayLgtSecCurrent = -3600;
        //        }
        date_default_timezone_set("GMT");
        $gmt = date("Y-m-d H:i:s ");

        $require_timezone = $timezoneRequired;
        date_default_timezone_set($require_timezone);
        $required = date("Y-m-d H:i:s ");
        /* Uncomment if daylight is required */
        //        $daylight_flag = date("I", strtotime($time));
        //        if ($daylight_flag == 1) {
        //            $dayLightFlag = true;
        //            $dayLgtSecReq = +3600;
        //        }

        date_default_timezone_set($system_timezone);

        $diff1 = (strtotime($gmt) - strtotime($local));
        $diff2 = (strtotime($required) - strtotime($gmt));

        $date = new DateTime($time);

        $date->modify("+$diff1 seconds");
        $date->modify("+$diff2 seconds");

        if ($dayLightFlag) {
            $final_diff = $dayLgtSecCurrent + $dayLgtSecReq;
            $date->modify("$final_diff seconds");
        }

        $timestamp = $date->format("Y-m-d H:i:s ");

        return $timestamp;
    }
}