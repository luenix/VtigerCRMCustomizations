<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

class SMSNotifier_ClickATell_Provider implements SMSNotifier_ISMSProvider_Model
{

    private $userName;
    private $password;
    private $parameters = array();

    const SERVICE_URI = 'http://api.clickatell.com';
    private static $REQUIRED_PARAMETERS = array('api_id', 'from', 'mo', 'callback');

    /**
     * Function to get provider name
     *
     * @return string provider name
     */
    public function getName()
    {
        return 'ClickATell';
    }

    /**
     * Function to get required parameters other than (userName, password)
     *
     * @return array required parameters list
     */
    public function getRequiredParams()
    {
        return self::$REQUIRED_PARAMETERS;
    }

    /**
     * Function to get service URL to use for a given type
     *
     * @param string|bool $type like SEND, PING, QUERY
     *
     * @return mixed
     */
    public function getServiceURL($type = false)
    {
        if ($type) {
            switch (strtoupper($type)) {
                case self::SERVICE_AUTH:
                    return self::SERVICE_URI . '/http/auth';
                case self::SERVICE_SEND:
                    return self::SERVICE_URI . '/http/sendmsg';
                case self::SERVICE_QUERY:
                    return self::SERVICE_URI . '/http/querymsg';
            }
        }

        return false;
    }

    /**
     * Function to set authentication parameters
     *
     * @param string $userName
     * @param string $password
     */
    public function setAuthParameters($userName, $password)
    {
        $this->userName = $userName;
        $this->password = $password;
    }

    /**
     * Function to set non-auth parameter.
     *
     * @param string $key
     * @param string $value
     */
    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    /**
     * Function to get parameter value
     *
     * @param string      $key
     * @param string|bool $defaultValue
     *
     * @return string value/$default value
     */
    public function getParameter($key, $defaultValue = false)
    {
        if (isset($this->parameters[$key])) {
            return $this->parameters[$key];
        }

        return $defaultValue;
    }

    /**
     * Function to prepare parameters
     *
     * @return array parameters
     */
    protected function prepareParameters()
    {
        $params = array('user' => $this->userName, 'password' => $this->password, 'callback' => '2', 'unicode' => '0', 'concat' => '3');
        foreach (self::$REQUIRED_PARAMETERS as $key) {
            $params[$key] = $this->getParameter($key);
        }

        return $params;
    }

    /**
     * Function to handle UTF-8 Check and conversion
     *
     * @author Nuri Unver
     *
     * @param string $data
     *
     * @return array
     */
    public function smstxtcode($data)
    {
        $mb_hex = '';
        $utf = 0;
        for ($i = 0; $i < mb_strlen($data, 'UTF-8'); $i++) {
            $c = mb_substr($data, $i, 1, 'UTF-8');

            $o = unpack('N', mb_convert_encoding($c, 'UCS-4BE', 'UTF-8'));
            $hx = sprintf('%04X', $o[1]);
            $utf += intval(substr($hx, 0, 2));
            $mb_hex .= $hx;
        }
        if ($utf > 0) {
            $return = $mb_hex;
            $utf = 1;
        } else {
            $return = utf8_decode($data);
            $utf = 0;
        }

        return array($utf, $return);

    }

    /**
     * Function to handle SMS Send operation
     *
     * @param string $message
     * @param mixed  $toNumbers One or Array of numbers
     *
     * @return array $results
     */
    public function send($message, $toNumbers)
    {
        if (!is_array($toNumbers)) {
            $toNumbers = array($toNumbers);
        }
        $params = $this->prepareParameters();
        $smsarray = $this->smstxtcode($message);
        $params['text'] = $smsarray[1];
        $params['unicode'] = $smsarray[0];
        $params['to'] = implode(',', $toNumbers);
        $serviceURL = $this->getServiceURL(self::SERVICE_SEND);
        $httpClient = new Vtiger_Net_Client($serviceURL);
        $response = $httpClient->doPost($params);
        $responseLines = explode("\n", $response);

        $results = array();
        $i = 0;
        foreach ($responseLines as $responseLine) {
            // echo "<script type='text/javascript'>alert('$responseLine');</script>";
            $responseLine = trim($responseLine);
            if (empty($responseLine)) {
                continue;
            }

            $result = array('error' => false, 'statusmessage' => '');
            if (preg_match("/ERR:(.*)/", trim($responseLine), $matches)) {
                $result['error'] = true;
                $result['to'] = $toNumbers[$i];
                $result['id'] = time();
                $result['statusmessage'] = $matches[0]; // Complete error message
                $result['status'] = "Failed";
            } else {
                if (preg_match("/ID: ([^ ]+)TO:(.*)/", $responseLine, $matches)) {
                    $result['id'] = trim($matches[1]);
                    $result['to'] = trim($matches[2]);
                    $result['status'] = self::MSG_STATUS_PROCESSING;
                } else {
                    if (preg_match("/ID: (.*)/", $responseLine, $matches)) {
                        $result['id'] = trim($matches[1]);
                        $result['to'] = $toNumbers[$i];
                        $result['status'] = self::MSG_STATUS_PROCESSING;
                    }
                }
            }
            $i++;
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Function to get query for status using message id
     *
     * @param <Number> $messageId
     *
     * @return array $result
     */
    public function query($messageId)
    {

        $params = $this->prepareParameters();
        $params['apimsgid'] = $messageId;

        $serviceURL = $this->getServiceURL(self::SERVICE_QUERY);
        $httpClient = new Vtiger_Net_Client($serviceURL);
        $response = $httpClient->doPost($params);
        $response = trim($response);

        $result = array('error' => false, 'needlookup' => 1, 'statusmessage' => '');

        if (preg_match("/ERR: (.*)/", $response, $matches)) {
            $result['error'] = true;
            $result['needlookup'] = 0;
            $result['statusmessage'] = $matches[0];
        } elseif (preg_match("/ID: ([^ ]+) Status: ([^ ]+)/", $response, $matches)) {
            $result['id'] = trim($matches[1]);
            $status = trim($matches[2]);

            // Capture the status code as message by default.
            $result['statusmessage'] = "CODE: $status";

            if ($status == '002' || $status == '008' || $status == '011') {
                $result['status'] = self::MSG_STATUS_PROCESSING;
            } else {
                if ($status == '003' || $status == '004') {
                    $result['status'] = self::MSG_STATUS_DISPATCHED;
                    $result['needlookup'] = 0;
                } else {
                    $statusMessage = '';
                    switch ($status) {
                        case '001':
                            $statusMessage = 'Message unknown';
                            break;
                        case '005':
                            $statusMessage = 'Error with message';
                            break;
                        case '006':
                            $statusMessage = 'User cancelled message delivery';
                            break;
                        case '007':
                            $statusMessage = 'Error delivering message';
                            break;
                        case '009':
                            $statusMessage = 'Routing error';
                            break;
                        case '010':
                            $statusMessage = 'Message expired';
                            break;
                        case '012':
                            $statusMessage = 'Out of credit';
                            break;
                    }

                    if (!empty($statusMessage)) {
                        $result['error'] = true;
                        $result['needlookup'] = 0;
                        $result['statusmessage'] = $statusMessage;
                    }
                }
            }
        }

        return $result;
    }
}
