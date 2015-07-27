<?php

class HeimirAPI
{
    const VERSION = '0.0.1';
    const HEIMIR_VERSION = '1000';

    public function __construct($plugin_dir, $username, $password, $processor, $merchantid, $terminalid, $RRN_Prefix, $mode='')
    {
        $this->merchantid = $merchantid;
        $this->processor = $processor;
        $this->terminalid = $terminalid;
        $this->RRN_Prefix = $RRN_Prefix;

        if ($mode == 'test') {
            $apiUrl = 'https://gatewaytest.borgun.is/';
        }
        else {
            $apiUrl = 'https://gateway01.borgun.is/';
        }


        $this->api = new SoapClient($plugin_dir . 'Heimir/heimir.wsdl', array(
                        "ssl_method" => SOAP_SSL_METHOD_SSLv3,
                        "trace" => 1,
                        "exception" => 0,
                        "location" => $apiUrl . 'ws/Heimir.pub.ws:Authorization/Heimir_pub_ws_Authorization_Port',
                        "login" => $username,
                        "password" => $password));


    }

    public function CreateXML($element, $values) {
                $writer = new XMLWriter();
                $writer->openMemory();
                    $writer->startDocument('1.0','UTF-8');
                        $writer->setIndent(4);
                        $writer->startElement($element);
                    foreach($values as $key => $value) {
                        if(strlen($value) > 0) {
                                $writer->startElement($key);
                                $writer->text($value);
                                $writer->endElement();
                        }
                    }
                    $writer->endElement();
                $writer->endDocument();

                return $writer->outputMemory();
    }


    public function getAuthorization($transtype, $amount, $trcurrency, $datetime, $pan, $expdate, $cvc2, $order_number, $newamount = Null, $auth_code = Null) {
        $valid_rrn = $this->RRN_Prefix . str_pad((string)$order_number, 12 - strlen($this->RRN_Prefix), "0", STR_PAD_LEFT);

        $xml = $this->CreateXML(__FUNCTION__, array('Version' => self::HEIMIR_VERSION,
                                                                                                          'Processor' => $this->processor,
                                                                                                          'MerchantID' => $this->merchantid,
                                                                                                          'TransType' => $transtype,
                                                                                                          'TrAmount' => $amount * 100,
                                                                                                          'NewAmount' => ($newamount == Null ? Null :  $newamount * 100),
                                                                                                          'TrCurrency' => $trcurrency,
                                                                                                          'DateAndTime' => $datetime,
                                                                                                          'PAN' => $pan,
                                                                                                          'ExpDate' => (strlen($expdate) > 4 ? substr($expdate, 4, 2) . substr($expdate, 0, 2) :  substr($expdate, 2, 2) . substr($expdate, 0, 2)),
                                                                                                          'AuthCode' => $auth_code,
                                                                                                          'CVC2' => $cvc2,
                                                                                                          'SecurityLevelInd' => '0',
                                                                                                  'RRN' => $valid_rrn,

                        ));
        $function = __FUNCTION__;
        $auth = simplexml_load_string($this->api->$function(array("getAuthReqXml" => $xml))->getAuthResXml);
        return $auth;
    }

    static function cancelAuthorization() {

    }

    static function sendDetailData() {

    }


}
