<?php

require_once('../class/Db.php');
require_once('../class/CoinGecko.php');

$coinGecko = new CoinGecko();
$tokens = $coinGecko->getTokenList();

foreach($tokens as $key => $token){
    $db = new Db();
    $db->query("
        INSERT INTO coingecko_list (token_id, token_symbol, token_name) 
        VALUES('$token->id', '$token->symbol', '$token->name') 
        ON DUPLICATE KEY 
        UPDATE token_symbol = '$token->id', token_name = '$token->name'
    ");
}
