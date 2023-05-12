<?php
/**
 * PHP library for Emome IMSP SMS's HTTP API
 *
 * @author Kun-Fu Tseng, Shu-Te University, http://www.stu.edu.tw
 * @editor John Chen, Tronice Co., Ltd., https://www.tronice.com
 * @license MIT License
 */

namespace EmomeIMSP;

require "ShortMessageProviderInterface.php";

class SMS implements ShortMessageProviderInterface
{
    protected $_host = "https://imsp.emome.net:4443/imsp/sms/servlet";
    protected $_parameters = [];

    protected $_msg_dcs = 8; //都以 Unicode 去處理

    /**
     * Constructor
     *
     * @param  string $account
     * @param  string $password
     * @return void
     */
    public function __construct($account, $password) {
        $this->_account = $account;
        $this->_password = $password;

        $this->_parameters = [
            "account"         => $account,
            "password"        => $password,
            "from_addr_type"  => 0,
            "from_addr"       => null,
            "to_addr_type"    => 0,
            "to_addr"         => null,
            "msg_expire_time" => 0,
            "msg_type"        => 0,
            "msg_pclid"       => 0,
            "msg_udhi"        => 0,
            "msg"             => null,
            "dest_port"       => 0
        ];

        $this->_parameters["msg_dcs"] = $this->_msg_dcs; //不讓用戶從外面變更
    }

    /**
     * Submit the SM via custom parameters.
     *
     * @param  array $params
     * @return array [to_addr, code, message_id, description]
     */
    public function submitSM($params = []) 
    {
        $params = array_merge($this->_parameters, $params);
        $params["msg"] = $this->convertMessage($params["msg"]);
        $params["dest_port"] = $this->convertDestPortByMessageType($params["dest_port"], $params["msg_type"]);
        $params["to_addr"] = $this->convertToAddr($params["to_addr"]);

        $url = "{$this->_host}/SubmitSM";
        $response = $this->makeHttpRequest($url, "POST", $params);
        return $this->_parseResponse($response);
    }

    /**
     * Old API, delegate to submitSM method.
     *
     * @param  string $message
     * @return string $to_addr
     * @since Nov 2010
     */
    public function sendSM($message, $to_addr) {
        return $this->submitSM([
            "msg" => $message,
            "to_addr" => $to_addr
        ]);
    }

    /**
     * Parse response from CHT Emome IMSP SubmitSM API.
     *
     * @param  array $params
     * @return array [to_addr, code, message_id, description]
     * @since Nov 2010
     */
    protected function _parseResponse($response) 
    {
        $response = preg_replace('/<br>$/', '', $response);
        $response = strip_tags($response, '<br>');
        $response = preg_replace('/[\r\n]*/', '', $response);
        $tmpResult = explode('<br>', $response);

        if ($tmpResult) {
            foreach ($tmpResult as $key => $val) {
                $x = explode('|', $val);
                $result[$x[0]] = $x;
            }
        }
        return $result;
    }

    /**
     * Return one or more mobile phone number concatenate string with comma.
     *
     * @param  array|string $to_addr
     * @return string 
     */
    protected function convertToAddr($to_addr)
    {
        if (is_array($to_addr)) {
            $to_addr = array_unique($to_addr);
        } else {
            $to_addr = array_unique(preg_split('/,\s*/', $to_addr));
        }

        $to_addr = implode(",", $to_addr);
        return $to_addr;
    }

    /**
     * Convert message
     *
     * @param  string $msg
     * @return string 
     */
    protected function convertMessage($msg) 
    {
        mb_internal_encoding("UTF-8");

        $msg = mb_convert_encoding($msg, "UTF-16BE", "UTF-8");
        $msg = strtoupper(bin2hex($msg));

        return $msg;
    }

    /**
     * Convert destination port to hexadecimal if message type code is 2 or 3.
     *
     * @param  string $dest_port
     * @param  string $msg_type
     * @return string 
     */
    protected function convertDestPortByMessageType($dest_port, $msg_type)
    {
        if ($msg_type == 2 || $msg_type == 3) {
            // e.g. 1234 => 04D2
            return strtoupper(sprintf('%04x', $dest_port));
        }
    }

    /**
     * Make an HTTP request
     *
     * @param string $url
     * @param string $method
     * @param null|array $postfields
     * @return Server API results
     */
    protected function makeHttpRequest($url, $method, $postfields = null) {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_USERAGENT, "Emome_IMSP_SMS");
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);

        if ($method == "POST") {
            curl_setopt($curl, CURLOPT_POST, TRUE);
            if (! empty($postfields)) {

                $keys = array_map("urlencode", array_keys($postfields));
                $values = array_map("urlencode", array_values($postfields));
                $postfields = array_combine($keys, $values);
                $fields_str = "";
                foreach ($postfields as $key => $value) { 
                    $fields_str .= "$key=$value&"; 
                }
                curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_str);
            }
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        $response = curl_exec($curl);
        curl_close ($curl);

        return $response;
    }

}
