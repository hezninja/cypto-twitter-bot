<?php

require_once('../../env.php');

class CoinMarketCap {
    private $apiKey;
    public $latestTokens;

    function __construct() {
        $this->apiKey = COINMARKETCAP_KEY;
        $this->latestTokens = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest?aux=date_added&sort=date_added&limit=100';
    }
    function getLatestTokens(){
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->latestTokens,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Accepts: application/json',
                'X-CMC_PRO_API_KEY: '.$this->apiKey
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response);
    }
}
