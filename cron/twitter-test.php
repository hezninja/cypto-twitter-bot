<?php

require_once('../class/Db.php');
require_once "../vendor/autoload.php";

use Abraham\TwitterOAuth\TwitterOAuth;

echo ACCESS_TOKEN;

$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, ACCESS_TOKEN, ACCESS_TOKEN_SECRET);

$status = "🚨🚨🚨 Ruggy just bought AngelHeart Token (AHT) for $0.00034864 🚀🚀🚀";
$post_tweets = $connection->post("statuses/update", ["status" => $status]);

print_r($post_tweets);
