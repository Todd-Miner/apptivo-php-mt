<?php

declare(strict_types=1);

namespace todd_miner\apptivo_php_mt;

class SkeletonClass
{
    /**  @var string $apiKey API Key for the business to be accessed */
    private $apiKey = '';
    /**  @var string $accessKey Access Key for the business to be accessed */
    private $accessKey = '';
    /**  @var string $apiUserEmail Email address of the employee who we should perform actions on behalf of */
    private $apiUserNameStr = '';
    
    function __construct($apiKey, $accessKey, $apiUserEmail) {
        $this->apiKey = $apiKey;
        $this->accessKey = $accessKey;
        if($apiUserEmail) {
            $this->apiUserNameStr = '&userName='.$apiUserEmail;
        }
    }
   
    public function getConfigById(string $appId) {
        //You can pass in an appId or appName.  If $appId is not an int then we'll get the app id from getAppParameters
        $objParams = $this->getAppParamters($appId);
        $appParts = explode('-',$appId);
        if(count($appParts) > 0) {
            $appId = $appParts[1];
        }else{
            $appId = $objParams['objectId'];
        }
        if(!intval($appId)) {
            //If we provide an app id we can ovverride it here.  This is used for custom apps, so a cases app extension uses app name Cases then the app id.
            $appId = $objParams['objectId'];
        }
        $returnedConfigData = '';
        foreach($this->configDataArr as $cConfig) {
            if($cConfig->appId == $appId) {
                $returnedConfigData = $cConfig->configData;
                return $returnedConfigData;
            }
        }
        if(!$returnedConfigData) {
            //logIt('We did not have any existing config stored for appId ('.$appId.').  Going to query from API.');
            $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.$objParams['objectUrlName'].'?a=getConfigData&objectId='.$appId.'&apiKey='.$this->apiKey.'&accessKey='.$this->accessKey.$this->userNameStr;
            //logIt($apiUrl,true,0);
            curl_setopt($this->ch,CURLOPT_URL, $apiUrl);
            $newConfigData = json_decode(curl_exec($this->ch));
            if($newConfigData) {
                $newConfigObj = new stdClass;
                $newConfigObj->appId = $appId;
                $newConfigObj->appName = $objParams['appName'];
                $newConfigObj->configData = $newConfigData;
                $this->configDataArr[] = $newConfigObj;
                //logIt('Returning newConfigData: '.json_encode($newConfigData));
                return $newConfigData;
            }else{
                logIt('ERROR: Unable to retrieve config data for appId ('.$appId.')');
                return false;
            }
        }
    }
}
