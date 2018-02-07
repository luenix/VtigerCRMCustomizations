<?php

class UpdateVtiger
{

    public static function getLeadPQuery(
        $parameters = array(),
        $leadID = null,
        $assignmentID = null
    ) {
        if (
            !count($parameters) ||
            $leadID === null ||
            $assignmentID === null
        ) {
            return false;
        }

        $pquery = array();

        foreach ($parameters as $key => $value) {
            if ($value) {
                switch ($key) {
                    case 'city':
                    case 'code':
                    case 'state':
                    case 'pobox':
                    case 'country':
                    case 'phone':
                    case 'mobile':
                    case 'fax':
                    case 'lane':
                    case 'leadaddresstype':
                        $pquery['vtiger_leadaddress'][$key] = $value;
                        break;
                    case 'email':
                    case 'interest':
                    case 'firstname':
                    case 'salutation':
                    case 'lastname':
                    case 'company':
                    case 'annualrevenue':
                    case 'industry':
                    case 'campaign':
                    case 'rating':
                    case 'leadstatus':
                    case 'leadsource':
                    case 'converted':
                    case 'designation':
                    case 'licencekeystatus':
                    case 'space':
                    case 'comments':
                    case 'priority':
                    case 'demorequest':
                    case 'partnercontact':
                    case 'productversion':
                    case 'product':
                    case 'maildate':
                    case 'nextstepdate':
                    case 'fundingsituation':
                    case 'purpose':
                    case 'evaluationstatus':
                    case 'transferdate':
                    case 'revenuetype':
                    case 'noofemployees':
                    case 'secondaryemail':
                    case 'assignleadchk':
                    case 'emailoptout':
                        $pquery['vtiger_leaddetails'][$key] = $value;
                        break;
                    case 'cf_943':
                        $pquery['vtiger_crmentity'][$key] = $value;
                    case 'cf_759':
                    case 'cf_761':
                    case 'cf_765':
                    case 'cf_835':
                    case 'cf_837':
                    case 'cf_839':
                    case 'cf_841':
                    case 'cf_843':
                    case 'cf_845':
                    case 'cf_847':
                    case 'cf_849':
                    case 'cf_853':
                    case 'cf_855':
                    case 'cf_857':
                    case 'cf_859':
                    case 'cf_861':
                    case 'cf_863':
                    case 'cf_865':
                    case 'cf_869':
                    case 'cf_871':
                    case 'cf_873':
                    case 'cf_875':
                    case 'cf_877':
                    case 'cf_879':
                    case 'cf_883':
                    case 'cf_887':
                    case 'cf_889':
                    case 'cf_891':
                    case 'cf_893':
                    case 'cf_895':
                    case 'cf_897':
                    case 'cf_899':
                    case 'cf_901':
                    case 'cf_903':
                    case 'cf_905':
                    case 'cf_907':
                    case 'cf_909':
                    case 'cf_913':
                    case 'cf_915':
                    case 'cf_917':
                    case 'cf_919':
                    case 'cf_921':
                    case 'cf_923':
                    case 'cf_925':
                    case 'cf_927':
                    case 'cf_929':
                    case 'cf_931':
                    case 'cf_933':
                    case 'cf_935':
                    case 'cf_937':
                    case 'cf_939':
                    case 'cf_941':
                    case 'cf_945':
                    case 'cf_947':
                    case 'cf_951':
                    case 'cf_953':
                    case 'cf_955':
                    case 'cf_957':
                    case 'cf_959':
                    case 'cf_961':
                    case 'cf_963':
                    case 'cf_965':
                    case 'cf_967':
                    case 'cf_969':
                    case 'cf_1100':
                    case 'cf_1102':
                    case 'cf_1104':
                    case 'cf_1106':
                    case 'cf_1108':
                    case 'cf_1295':
                    case 'cf_1297':
                    case 'cf_1299':
                    case 'cf_1303':
                    case 'cf_1311':
                    case 'cf_1313':
                    case 'cf_1315':
                    case 'cf_1317':
                    case 'cf_1333':
                    case 'cf_1355':
                    case 'cf_1369':
                    case 'cf_1421':
                    case 'cf_1427':
                    case 'cf_1469':
                    case 'cf_1471':
                    case 'cf_1525':
                    case 'cf_1527':
                    case 'cf_1529':
                    case 'cf_1531':
                    case 'cf_1533':
                    case 'cf_1535':
                    case 'cf_1537':
                    case 'cf_1539':
                    case 'cf_1545':
                    case 'cf_1611':
                    case 'cf_1613':
                    case 'cf_1615':
                    case 'cf_1617':
                    case 'cf_1621':
                    case 'cf_1623':
                    case 'cf_1625':
                    case 'cf_1627':
                    case 'cf_1629':
                    case 'cf_1631':
                    case 'cf_1633':
                    case 'cf_1811':
                    case 'cf_1889':
                    case 'cf_1891':
                    case 'cf_1893':
                    case 'cf_1895':
                    case 'cf_1897':
                    case 'cf_1899':
                    case 'cf_1931':
                    case 'cf_2155':
                    case 'cf_2157':
                    case 'cf_2159':
                    case 'cf_2161':
                        $pquery['vtiger_leadscf'][$key] = $value;
                        break;
                    default:
                        break;
                } // END switch ($key)
            } // END if ($value)
        } // END foreach ($webform_parameters as $key => $value)

        if (count($pquery['vtiger_crmentity'])) {
            $pquery['pquery_vtiger_crmentity'] = "UPDATE vtiger_crmentity SET smownerid=" . $assignmentID . " WHERE crmid=" . $leadID;
        } // END if (count($pquery['vtiger_crmentity']))

        if (count($pquery['vtiger_leadaddress'])) {
            $pquery['pquery_vtiger_leadaddress'] = "UPDATE vtiger_leadaddress SET";
            foreach ($pquery['vtiger_leadaddress'] as $key => $value) {
                $pquery['pquery_vtiger_leadaddress'] .= ' ' . $key . "='" . $value . "',";
            } // END foreach ($pquery['vtiger_leadaddress'] as $key => $value)
            $pquery['pquery_vtiger_leadaddress'] = rtrim($pquery['pquery_vtiger_leadaddress'], ",") . " WHERE leadaddressid=" . $leadID;
        } // END if (count($pquery['vtiger_leadaddress']))

        if (count($pquery['vtiger_leaddetails'])) {
            $pquery['pquery_vtiger_leaddetails'] = "UPDATE vtiger_leaddetails SET";
            foreach ($pquery['vtiger_leaddetails'] as $key => $value) {
                $pquery['pquery_vtiger_leaddetails'] .= ' ' . $key . "='" . $value . "',";
            } // END foreach ($pquery['vtiger_leaddetails'] as $key => $value)
            $pquery['pquery_vtiger_leaddetails'] = rtrim($pquery['pquery_vtiger_leaddetails'], ",") . " WHERE leadid=" . $leadID;
        } // END if (count($pquery['vtiger_leaddetails']))

        if (count($pquery['vtiger_leadscf'])) {
            $pquery['pquery_vtiger_leadscf'] = "UPDATE vtiger_leadscf SET";
            foreach ($pquery['vtiger_leadscf'] as $key => $value) {
                $pquery['pquery_vtiger_leadscf'] .= ' ' . $key . "='" . $value . "',";
            } // END foreach ($pquery['vtiger_leadscf'] as $key => $value)
            $pquery['pquery_vtiger_leadscf'] = rtrim($pquery['pquery_vtiger_leadscf'], ",") . " WHERE leadid=" . $leadID;
        } // END if (count($pquery['vtiger_leadscf']))

        return $pquery;
    }
}
