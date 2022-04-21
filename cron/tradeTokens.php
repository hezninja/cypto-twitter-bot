<?php
date_default_timezone_set("Australia/Brisbane");

require_once('../class/Db.php');
require_once('../class/CoinGecko.php');
require_once('../class/CoinMarketCap.php');
require_once('../class/OneSignal.php');

require_once ('../vendor/autoload.php');
use Abraham\TwitterOAuth\TwitterOAuth;

$config = array(
    'min_age'           => 2,        // Minimum age of token (days)
    'max_age'           => 5,        // Maximum age added to CoinMarketCap
    'max_history'       => 60,       // Maximum historical data to harvest (days)
    'ma_short'          => 12,       // Short term moving average (Hours)
    'ma_long'           => 26,       // Long term moving average (Hours)
    'signal'            => 9,        // MACD Signal
    'moon_limit'        => 400,      // Maximum ATH Percent gains since launch
    'dead_cat_limit'    => 25,       // Maximum percentage decline from ATH
    'signal_divergence' => 36,       // Minimum percentage difference between MACD and Signal line
    'cg_vs'             => 'usd',    // CoinGecko currency
    'cg_interval'       => 'hourly', // CoinGecko price interval
);

function epochToDateHour($epoch){
    return date("Y-m-d H:00:00", substr($epoch, 0, 10));
}

function percentChange($a, $b){
    $diff = $a - $b;
    $diff = abs($diff);

    return ($diff/$a)*100;
}

function getSMA($array, $key, $period){
    if($key >= $period - 1){
        $sma = array_slice($array, ($key - $period) + 1, $period, true);
        $smaArray = array();

        foreach ($sma as $n => $v) {
            $smaArray[] = $v;
        }

        return array_sum($smaArray) / $period;
    }else{
        return false;
    }
}

function movingAverages($name, $symbol, $slug, $added, $dates, $prices){
    global $config;

    $movingAverages = array();
    $macdArray = array();
    $signal = array();

    foreach ($prices as $key => $price){
        $ma_short = getSMA($prices, $key, $config['ma_short']);
        $ma_long = getSMA($prices, $key, $config['ma_long']);
        $macd = $ma_short - $ma_long;
        $macdArray[] = $macd;

        $movingAverages[] = array(
            "short" => sprintf('%.12f',floatval($ma_short)),
            "long"  => sprintf('%.12f',floatval($ma_long)),
            "macd"  => sprintf('%.12f',floatval($macd))
        );
    }

    foreach($macdArray as $key => $period){
        $s = getSMA($macdArray, $key, $config['signal']);
        $signal[] = sprintf('%.12f',floatval($s));
    }

    return array(
        'name' => $name,
        'symbol' => $symbol,
        'slug' => $slug,
        'added' => $added,
        'date' => $dates,
        'price' => $prices,
        'moving_average' => $movingAverages,
        'signal' => $signal);
}

function moonCheck($priceData){
    global $config;

    $deadCat = percentChange(max($priceData), end($priceData));
    $moonVisit = percentChange($priceData[0], max($priceData));

    if($deadCat > $config['dead_cat_limit']){
        return array('failed' => 'did not pass dead cat test. Skipping...');
    }

    if($moonVisit > $config['moon_limit']){
        return array('failed' => 'did not pass moon test. Skipping...');
    }

    return array(
        'passed' => "passed Moon Test. ATH is $moonVisit% from launch price. Current price is $deadCat% from ATH.",
        "moon_visit" => $moonVisit,
        "dead_cat" => $deadCat
    );
}

function checkOpenTrade($token){
    $db  = new Db();
    $sql = $db->query("
        SELECT * 
        FROM trades 
        WHERE token_id = '$token' 
        AND sell_date = 0
    ");

    if($sql === false)
        return false;

    while ($row = $sql->fetch_assoc()) {
        return $row['id'];
    }

    return false;
}

function getAllOpenTrades(){
    $db  = new Db();
    $sql = $db->query("
        SELECT trades.token_id, token_name, token_symbol 
        FROM trades 
        LEFT JOIN coingecko_list 
        ON trades.token_id = coingecko_list.token_id 
        WHERE sell_date = 0
    ");

    if($sql === false)
        return false;

    $openTrades = array();

    while ($row = $sql->fetch_assoc()) {
        $openTrades[] = array(
            'token_name' => $row['token_name'],
            'token_symbol' => $row['token_symbol'],
            'token_id' => $row['token_id']
        );
    }

    return $openTrades;
}

function saveTrade($tokenId, $tradeType, $price, $date, $macd, $signal, $openTrade = null, $moonVisit = null, $deadCat = null){
    $db = new Db();

    if($tradeType == 'buy'){
        $db->query("
            INSERT INTO trades (token_id, buy_date, buy_price, buy_macd, buy_signal, moon_visit, dead_cat) 
            VALUES('$tokenId', '$date', '$price', '$macd', '$signal', '$moonVisit', '$deadCat')
        ");
    }else if($tradeType == 'sell'){
        $db->query("
            UPDATE trades SET sell_price = '$price', sell_date = '$date', sell_macd = '$macd', sell_signal = '$signal'
            WHERE id = '$openTrade'
        ");
    }

    return true;
}

function tradeToken($token, $moonVisit = null, $deadCat = null){
    global $config;

    if(end($token['date']) == $token['date'][count($token['date']) -2]){
        $currentKey = count($token['date']) - 2;
    }else{
        $currentKey = count($token['date']) - 1;
    }

    $openTrade = checkOpenTrade($token['slug']);
    $previousKey = $currentKey - 1;
    $ma_short_current  = $token['moving_average'][$currentKey]['short'];
    $ma_long_current   = $token['moving_average'][$currentKey]['long'];
    $ma_short_previous = $token['moving_average'][$previousKey]['short'];
    $ma_long_previous  = $token['moving_average'][$previousKey]['long'];
    $macd = $token['moving_average'][$currentKey]['macd'];
    $signal = $token['signal'][$currentKey];
    $minPeriod = $config['signal'] + $config['ma_long'];

    if($macd > 0 && $macd > ($signal * ($config['signal_divergence'] / 100)) + $signal && $currentKey > $minPeriod && !$openTrade){
        saveTrade($token['slug'], 'buy', $token['price'][$currentKey], $token['date'][$currentKey], $macd, $signal, null, $moonVisit, $deadCat);
        $os = new OneSignal();
        $os->sendNotification("Let's go to the moon, boys! 🚀🚀🚀", "🚨 ".$token['name']." (".$token['symbol'].") BUY ALERT");

        $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
        $status = "🚨🚨🚨 Ruggy just bought ".$token['name']." (".$token['symbol'].") for $".$token['price'][$currentKey]." 🚀🚀🚀";
        $connection->post("statuses/update", ["status" => $status]);

        echo "Bought ".$token['name'];
    }

    if($ma_short_current < $ma_long_current && $ma_short_previous > $ma_long_previous && $macd < 0 && $openTrade){
        saveTrade($token['slug'], 'sell', $token['price'][$currentKey], $token['date'][$currentKey], $macd, $signal, $openTrade);
        $os = new OneSignal();
        $os->sendNotification("Cash it in, boys! 💵💵💵", "🚨 ".$token['name']." (".$token['symbol'].") SELL ALERT");

        $connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);
        $status = "🚨🚨🚨 Ruggy just sold ".$token['name']." (".$token['symbol'].") for $".$token['price'][$currentKey]." 💵💵💵";
        $connection->post("statuses/update", ["status" => $status]);

        echo "Sold ".$token['name'];
    }else if($openTrade){
        echo "Hodling ".$token['name'];
    }

    return true;
}

function latestTokens(){
    global $config;

    $minAge = date('Y-m-d H:i:s', strtotime("-".$config['min_age']." day"));
    $maxAge = date('Y-m-d H:i:s', strtotime("-".$config['max_age']." day"));

    $coinMarketCap = new CoinMarketCap;
    $tokens = $coinMarketCap->getLatestTokens();

    $latestTokens = array();

    if(isset($tokens->data)){
        foreach($tokens->data as $key => $token){
            $dateAdded = date("Y-m-d H:i:s", strtotime($token->date_added));

            if($dateAdded < $minAge && $dateAdded > $maxAge){
                $latestTokens[] = $token;
            }
        }
    }else{
        echo 'Error collecting tokens from CoinMarketCap.<br/>';
    }

    return $latestTokens;
}

function analyseTokenHistory($latestTokens){
    global $config;

    foreach($latestTokens as $key => $token){
        $coinGecko = new CoinGecko();
        $tokenId = $coinGecko->getTokenIdFromName($token->name);

        if($tokenId){
            $coinGecko->setToken($tokenId);
            $tokenPrices = $coinGecko->getTokenChart($config['max_history'], $config['cg_interval'], $config['cg_vs']);

            if(isset($tokenPrices->prices)){
                $dateData  = array();
                $priceData = array();

                foreach($tokenPrices->prices as $interval => $data){
                    $dateData[]  = epochToDateHour($data[0]);
                    $priceData[] = sprintf('%.12f',floatval($data[1]));
                }

                $moonCheck = moonCheck($priceData);

                if(!isset($moonCheck['failed'])){
                    echo $token->name.' '.$moonCheck['passed'].'<br/>';
                    $completeToken = movingAverages($token->name, $token->symbol, $token->slug, $token->date_added, $dateData, $priceData);

                    tradeToken($completeToken, $moonCheck['moon_visit'], $moonCheck['dead_cat']);
                }else{
                    echo $token->name.' '.$moonCheck['failed'].'<br/>';
                }
            }else{
                echo $token->name.' not found on CoinGecko. Skipping...<br/>';
            }

        }
    }

    $openTrades = getAllOpenTrades();

    foreach($openTrades as $key => $token){
        echo "analysing open trade on ".$token['token_name'];
        $coinGecko = new CoinGecko();

        $coinGecko->setToken($token['token_id']);
        $tokenPrices = $coinGecko->getTokenChart($config['max_history'], $config['cg_interval'], $config['cg_vs']);

        $dateData  = array();
        $priceData = array();

        foreach($tokenPrices->prices as $interval => $data){
            $dateData[]  = epochToDateHour($data[0]);
            $priceData[] = sprintf('%.12f',floatval($data[1]));
        }

        $completeToken = movingAverages($token['token_name'], $token['token_symbol'], $token['token_id'], null, $dateData, $priceData);
        tradeToken($completeToken);
    }

    return true;
}

analyseTokenHistory(latestTokens());
