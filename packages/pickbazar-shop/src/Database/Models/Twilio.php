<?php

namespace PickBazar\Database\Models;

use Illuminate\Database\Eloquent\Model;

class Twilio
{

    private $sid;
    private $token;
    private $messageServiceSid;

    function __construct($user, $token,$messageServiceSid)
    {
        $this->sid = $user;
        $this->token = $token;
        $this->messageServiceSid = $messageServiceSid;
    }
    
    function sendSMS($destination, $message)
    {
        $params = ['To' => $destination, 'MessagingServiceSid' => $this->messageServiceSid, 'Body' => $message];
        $auth = $this->sid.':'.$this->token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.twilio.com/2010-04-01/Accounts/ACdf6687f13a3ffd7e69f581319bc85f9d/Messages.json');
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_USERPWD, $auth);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        $data = curl_exec($ch);

        //curl_close($ch);

    }
}