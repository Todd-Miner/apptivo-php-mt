<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\ApptivoPhp\AppParams;
use ToddMinerTech\ApptivoPhp\ObjectCrud;
use ToddMinerTech\ApptivoPhp\ObjectDataUtils;
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
    /**  @var int $apiSleepTime The global wait time to be applied before executing an api call.  Prevents rate limiting by the Apptivo API. */
    public $apiSleepTime = 1;
    /**  @var int $apiRetries The global number of retries to apply when an api call appears to fail.  This helps recover from getting rate limimted. */
    public $apiRetries = 1;
    
    function __construct(string $apiKey, string $accessKey, string $apiUserEmail) {
        $this->apiKey = $apiKey;
        $this->accessKey = $accessKey;
        if($apiUserEmail) {
            $this->apiUserNameStr = '&userName='.$apiUserEmail;
        }else{
            $this->apiUserNameStr = '';
        }
    }
    
    /**
     * getConfigData
     *
     * @param string $appNameOrId App name, app id, or combo string for extended apps (cases-993829).
     *
     * @return object Returns object containing the configuration for the app, or null if unable to locate
     */
    public function getConfigData(string $appNameOrId): object
    {
        $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
        $appParts = explode('-',$appNameOrId);
        if(count($appParts) > 1) {
            $appId = $appParts[1];
        }else{
            $appId = $appParams->objectId;
        }
        if(!intval($appId)) {
            //If we provide an app id we can ovverride it here.  This is used for custom apps, so a cases app extension uses app name Cases then the app id.
            $appId = $appParams->objectId;
        }
        $existingConfigData = '';
        foreach($this->configDataArr as $cConfig) {
            if($cConfig->appId == $appId) {
                $existingConfigData = $cConfig->configData;
                return $existingConfigData;
            }
        }
        $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.$appParams->objectUrlName.'?a=getConfigData&objectId='.$appId.'&apiKey='.$this->apiKey.'&accessKey='.$this->accessKey.$this->apiUserNameStr;
        $client = new \GuzzleHttp\Client();
        sleep($this->apiSleepTime);
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
        return null;
    }
    
    public function getById(string $appNameOrId, string $objectId): object 
    {
        return \ToddMinerTech\ApptivoPhp\ObjectCrud::getById($appNameOrId, $objectId, $this);
    }
    
    public function getAttrDetailsFromLabel(array $fieldLabel, object $inputObj, string $appNameOrId): ?object 
    {
        $configData = $this->getConfigData($appNameOrId);
        return \ToddMinerTech\ApptivoPhp\ObjectDataUtils::getAttrDetailsFromLabel($fieldLabel, $inputObj, $configData);
    }
    
    public function createNewAttrObjFromLabelAndValue(array $fieldLabel, array $newValue, string $appNameOrId): object 
    {
        $configData = $this->getConfigData($appNameOrId);
        return \ToddMinerTech\ApptivoPhp\ObjectDataUtils::createNewAttrObjFromLabelAndValue($fieldLabel, $newValue, $configData);
    }
    
    public function getApiKey() {
        return $this->apiKey;
    }
    public function getAccessKey() {
        return $this->accessKey;
    }
    public function getUserNameStr() {
        return $this->apiUserNameStr;
    }
}
