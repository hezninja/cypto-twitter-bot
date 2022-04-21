<?php

require_once('Db.php');

class CoinGecko {
    public $tokenChart;
    public $tokenList = "https://api.coingecko.com/api/v3/coins/list";

    function __construct($token = NULL) {
        $this->setToken($token);
    }
    function setToken($token){
        $this->tokenChart = "https://api.coingecko.com/api/v3/coins/$token/market_chart";
    }
    function getTokenList(){
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->tokenList,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response);
    }
    function getTokenChart($days, $interval, $currency){
        $curl   = curl_init();
        $params = http_build_query(
            array(
                'days'        => $days,
                'interval'    => $interval,
                'vs_currency' => $currency
            )
        );
        $request = "{$this->tokenChart}?{$params}";

        curl_setopt_array($curl, array(
            CURLOPT_URL => $request,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response);
    }
    function getTokenIdFromName($tokenName){
        $db  = new Db();
        $sql = $db->query("SELECT * FROM coingecko_list WHERE token_name = '$tokenName'");

        if($sql === false)
            return false;

        while ($row = $sql->fetch_assoc()) {
            return $row['token_id'];
        }

        return false;
    }
}
