<?php

# Required File Includes
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
/*
function verify_data($order_data) {
    //sig_format = '|'.join([order_data["tradeNo"], order_data["desc"].decode("utf-8"), order_data["time"], order_data["username"], order_data["userid"], str(order_data["amount"]), order_data["status"], PUSH_STATE_KEY]).encode("utf-8")
    $string = $order_data["tradeNo"] . '|' . $order_data["desc"] . '|' . $order_data["time"] . '|' . $order_data["username"] . '|' . $order_data["userid"] . '|' . (string)$order_data["amount"] . '|' . $order_data['security_code'];
    $sig    = strtoupper(md5($string));
    log_result(json_encode($order_data));
    log_result($sig . '==' . $order_data['sig']);
    if ($order_data["tradeNo"] && $order_data['sig'] == $sig) {
        return true;
    }
}
*/
function verify_post($order_data, $key) {
    log_result(json_encode($order_data));
    log_result($key);
    if ($order_data['money'] > 0 && !empty($order_data['trade_no']) && !empty($order_data['key']) && $order_data['key'] == $key) {
            $invoiceid = substr(strrchr($order_data['out_trade_no'], "|"), 1);
            if ($invoiceid > 0) {
                $order_data['invoice_id'] = $invoiceid;
                $order_data['status']     = 'success';
                log_result($order_data);

                return $order_data;
            }
    }

    return false;
}

function log_result($word) {
    $file = 'logs/alipay_log.txt';
    if (!is_string($word)) {
        $word = json_encode($word);
    }
    $string = strftime("%Y%m%d%H%I%S", time()) . "\t" . $word . "\n";
    file_put_contents($file, $string, FILE_APPEND);
}

$gatewaymodule = "alipay"; # Enter your gateway module name here replacing template
$GATEWAY       = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"])
    die("Module Not Activated"); # Checks gateway module is active before accepting callback

$order_data                  = $_POST;
$gatewaySELLER_EMAIL         = $GATEWAY['account'];
$gatewaySECURITY_CODE        = $GATEWAY['key'];
$order_data = verify_post($order_data, $gatewaySECURITY_CODE);
if (!$order_data) {
    logTransaction($GATEWAY["name"], $_POST, "Unsuccessful");
    echo 'faild';
    exit;
}

# Get Returned Variables
$status    = $order_data['status'];         //获取传递过来的交易状态
$invoiceid = $order_data['invoice_id'];     //订单号
$transid   = $order_data['trade_no'];       //转账交易号
$amount    = $order_data['money'];          //获取递过来的总价格
$fee       = 0;
if ($status == 'success') {
    $invoiceid = checkCbInvoiceID($invoiceid, $GATEWAY["name"]); 
    $table  = "tblaccounts";
    $fields = "transid";
    $where  = array("transid" => $transid);
    $result = select_query($table, $fields, $where);
    $data   = mysql_fetch_array($result);
    if (!$data) {
        addInvoicePayment($invoiceid, $transid, $amount, $fee, $gatewaymodule);
        logTransaction($GATEWAY["name"], $_POST, "Successful");
    }
    echo "success";
} else {
    echo 'faild';
}

?>