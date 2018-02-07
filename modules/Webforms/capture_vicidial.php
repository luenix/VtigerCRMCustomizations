<?php

// Switch the working directory to base
chdir(dirname(__FILE__) . '/../..');

include_once 'include/dev/UpdateVtiger.php';
include_once 'include/Zend/Json.php';
include_once 'vtlib/Vtiger/Module.php';
include_once 'include/utils/VtlibUtils.php';
include_once 'include/Webservices/Create.php';
include_once 'modules/Webforms/model/WebformsModel.php';
include_once 'modules/Webforms/model/WebformsFieldModel.php';
include_once 'include/QueryGenerator/QueryGenerator.php';
include_once 'includes/main/WebUI.php';

class Webform_Capture
{

    function captureNow($request)
    {
        global $adb;

        $currentLanguage = Vtiger_Language_Handler::getLanguage();
        $moduleLanguageStrings = Vtiger_Language_Handler::getModuleStringsFromFile($currentLanguage);
        vglobal('app_strings', $moduleLanguageStrings['languageStrings']);

        $returnURL = false;
        try {
            if (!vtlib_isModuleActive('Webforms')) {
                throw new Exception('webforms is not active');
            }

            $webform = Webforms_Model::retrieveWithPublicId(vtlib_purify($request['publicid']));
            if (empty($webform)) {
                throw new Exception("Webform not found.");
            }

            $returnURL = $webform->getReturnUrl();
            $roundrobin = $webform->getRoundrobin();

            // Retrieve user information
            $user = CRMEntity::getInstance('Users');
            $user->id = $user->getActiveAdminId();
            $user->retrieve_entity_info($user->id, 'Users');

            // Prepare the parametets
            $parameters = array();
            $webformFields = $webform->getFields();
            foreach ($webformFields as $webformField) {
                if ($webformField->getDefaultValue() != null) {
                    $parameters[$webformField->getFieldName()] = decode_html($webformField->getDefaultValue());
                } else {
                    $webformNeutralizedField = html_entity_decode($webformField->getNeutralizedField(), ENT_COMPAT, "UTF-8");
                    if (is_array(vtlib_purify($request[$webformNeutralizedField]))) {
                        $fieldData = implode(" |##| ", vtlib_purify($request[$webformNeutralizedField]));
                    } else {
                        $fieldData = vtlib_purify($request[$webformNeutralizedField]);
                        $fieldData = decode_html($fieldData);
                    }

                    $parameters[$webformField->getFieldName()] = stripslashes($fieldData);
                }
                if ($webformField->getRequired()) {
                    if (!isset($parameters[$webformField->getFieldName()])) {
                        throw new Exception("Required fields not filled");
                    }
                }
            }

            if ($roundrobin) {
                $ownerId = $webform->getRoundrobinOwnerId();
                $ownerType = vtws_getOwnerType($ownerId);
                $parameters['assigned_user_id'] = vtws_getWebserviceEntityId($ownerType, $ownerId);
            } else {
                $ownerId = $webform->getOwnerId();
                $ownerType = vtws_getOwnerType($ownerId);
                $parameters['assigned_user_id'] = vtws_getWebserviceEntityId($ownerType, $ownerId);
            }

            $leadID = null;
            if (isset($_POST['srmleadid'])) {
                $srmleadid = $_POST['srmleadid'];
                $result = $adb->pquery("SELECT leadid FROM vtiger_leaddetails WHERE lead_no=?", array($srmleadid));
                $result = $adb->fetch_array($result);
                $leadID = $result['leadid'];
                $result = $adb->pquery("SELECT id FROM vtiger_users WHERE user_name=?", array($parameters['cf_943']));
                $result = $adb->fetch_array($result);
                $assignmentID = $result['id'];
                //echo '$srmleadid: ' . $srmleadid . '<br>';
                //echo '$leadID: ' . $leadID . '<br>';
            }

            if ($leadID) {
                // Update the record
                // echo 'Would update!' . '<br>';
                // print_r($parameters);
                // echo '<br>';

                $pquery = UpdateVtiger::getLeadPQuery($parameters, $leadID, $assignmentID);

                try {
                    $hostname = 'localhost';
                    $username = 'srmadmin';
                    $password = 'Fuck0ffPhunk!';
                    $dbname = 'srm';

                    // Connect to MySQL DB `SRM`
                    $DBH = new PDO('mysql:host=' . $hostname . ';dbname=' . $dbname . ';charset=utf8', $username, $password);
                    $DBH->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    if (isset($pquery['pquery_vtiger_crmentity'])) {
                        // echo $pquery['pquery_vtiger_crmentity'] . '<br>';
                        $stmt = $DBH->prepare($pquery['pquery_vtiger_crmentity']);
                        $DBH->beginTransaction();
                        $stmt->execute();
                        $DBH->commit();
                    }

                    if (isset($pquery['pquery_vtiger_leadaddress'])) {
                        // echo $pquery['pquery_vtiger_leadaddress'] . '<br>';
                        $stmt = $DBH->prepare($pquery['pquery_vtiger_leadaddress']);
                        $DBH->beginTransaction();
                        $stmt->execute();
                        $DBH->commit();
                    }

                    if (isset($pquery['pquery_vtiger_leaddetails'])) {
                        // echo $pquery['pquery_vtiger_leaddetails'] . '<br>';
                        $stmt = $DBH->prepare($pquery['pquery_vtiger_leaddetails']);
                        $DBH->beginTransaction();
                        $stmt->execute();
                        $DBH->commit();
                    }

                    if (isset($pquery['pquery_vtiger_leadscf'])) {
                        // echo $pquery['pquery_vtiger_leadscf'] . '<br>';
                        $stmt = $DBH->prepare($pquery['pquery_vtiger_leadscf']);
                        $DBH->beginTransaction();
                        $stmt->execute();
                        $DBH->commit();
                    }

                    $DBH = null;
                } catch (PDOException $e) {
                    echo 'PDO error occurred.' . '<br>' . 'Error message: ' . $e->getMessage() . '<br>' . 'File: ' . $e->getFile() . '<br>' . 'Line: ' . $e->getLine() . '<br>';
                    $DBH->rollBack();
                    $DBH = null;
                }

            } else {
                // Create the record
                // echo 'Would create!' . '<br>';
                // print_r($parameters);
                // echo '<br>';
                $record = vtws_create($webform->getTargetModule(), $parameters, $user);
            }

            $this->sendResponse($returnURL, 'ok');

            return;

        } catch (Exception $e) {
            $this->sendResponse($returnURL, false, $e->getMessage());

            return;
        }
    }

    protected function sendResponse($url, $success = false, $failure = false)
    {
        if (empty($url)) {
            if ($success) {
                $response = Zend_Json::encode(array('success' => true, 'result' => $success));
            } else {
                $response = Zend_Json::encode(array('success' => false, 'error' => array('message' => $failure)));
            }

            // Support JSONP
            if (!empty($_POST['callback'])) {
                $callback = vtlib_purify($_POST['callback']);
                // echo sprintf("%s(%s)", $callback, $response);
            } else {
                echo $response;
            }
        } else {
            $pos = strpos($url, 'http');
            if ($pos !== false) {
                header(sprintf("Location: %s?%s=%s", $url, ($success ? 'success' : 'error'), ($success ? $success : $failure)));
            } else {
                header(sprintf("Location: http://%s?%s=%s", $url, ($success ? 'success' : 'error'), ($success ? $success : $failure)));
            }
        }
    }
}

// NOTE: Take care of stripping slashes...
$webformCapture = new Webform_Capture();
$webformCapture->captureNow($_POST);

