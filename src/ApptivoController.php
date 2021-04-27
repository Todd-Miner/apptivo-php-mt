<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\ApptivoPhp\AppParams;
use GuzzleHttp\Psr7\Request;

/**
 * Class ApptivoController
 *
 * Controls all Apptivo API queries and data handling functions
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class ApptivoController
{
    /**  @var string $apiKey API Key for the business to be accessed */
    private $apiKey;
    /**  @var string $accessKey Access Key for the business to be accessed */
    private $accessKey;
    /**  @var string $apiUserEmail Email address of the employee who we should perform actions on behalf of */
    private $apiUserNameStr;
    /**  @var array $configDataArr Stores an array of json config data objects queried from API to prevent multiple queries */
    private $configDataArr = [];
    
    function __construct(string $apiKey, string $accessKey, string $apiUserEmail) {
        $this->apiKey = $apiKey;
        $this->accessKey = $accessKey;
        if($apiUserEmail) {
            $this->apiUserNameStr = '&userName='.$apiUserEmail;
        }
    }
    
    /**
     * getConfigById
     *
     * @param string $appIdOrName App name, app id, or combo string for extended apps (cases-993829).
     *
     * @return object Returns object containing the configuration for the app, or null if unable to locate
     */
    public function getConfigById(string $appIdOrName) {
        $appParams = new AppParams($appIdOrName);
        $appParts = explode('-',$appIdOrName);
        if(count($appParts) > 1) {
            $appId = $appParts[1];
        }else{
            $appId = $appParams->objectId;
        }
        if(!intval($appId)) {
            //If we provide an app id we can ovverride it here.  This is used for custom apps, so a cases app extension uses app name Cases then the app id.
            $appId = $appParams->objectId;
        }
        $returnedConfigData = '';
        foreach($this->configDataArr as $cConfig) {
            if($cConfig->appId == $appId) {
                $returnedConfigData = $cConfig->configData;
                return $returnedConfigData;
            }
        }
        if(!$returnedConfigData) {
            $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.$appParams->objectUrlName.'?a=getConfigData&objectId='.$appId.'&apiKey='.$this->apiKey.'&accessKey='.$this->accessKey.$this->apiUserNameStr;
            $client = new \GuzzleHttp\Client();
            $res = $client->request('GET', $apiUrl);
            $body = $res->getBody();
            $bodyContents = $body->getContents();
            $newConfigData = json_decode($bodyContents);
            if($newConfigData) {
                $newConfigObj = new \stdClass();
                $newConfigObj->appId = $appId;
                $newConfigObj->appName = $appParams->appName;
                $newConfigObj->configData = $newConfigData;
                $this->configDataArr[] = $newConfigObj;
                return $newConfigData;
            }
        }
        return null;
    }
}
