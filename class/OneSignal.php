<?php

require_once('../../env.php');

class OneSignal{
    private $appId;
    private $apiKey;

    function __construct() {
        $this->appId = ONESIGNAL_APP_ID;
        $this->apiKey = ONESIGNAL_APP_KEY;
    }

    function sendNotification($message, $title = null, $segments = ['All']) {
        $fields = [
            'app_id' => $this->appId,
            'included_segments' => $segments,
            'contents' => [
                'en' => $message
            ],
            'url' => URI,
        ];

        if(isset($title))
            $fields['headings'] = ['en' => $title];

        $fields = json_encode($fields);
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_URL => 'https://onesignal.com/api/v1/notifications',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Basic ' . $this->apiKey
            ]
        ]);

        $resp = curl_exec($curl);
        curl_close($curl);

        return json_decode($resp, true);
    }
}
