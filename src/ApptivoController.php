<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\ApptivoPhp\AppParams;
use ToddMinerTech\ApptivoPhp\ObjectCrud;
use ToddMinerTech\ApptivoPhp\ObjectDataUtils;
use ToddMinerTech\ApptivoPhp\SystemUtils;
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
    /**  @var string $sessionEmailId Email address of the session we authenticated */
    private $sessionEmailId;
    /**  @var string $sessionPassword Matching password for session email */
    private $sessionPassword;
    /**  @var string $firmId Firm id for the session authentication */
    private $firmId;
    /**  @var string $sessionKey Session key from authentication */
    public $sessionKey = '';
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
    
    /* 
     * setSessionCredentials Most endpoints work fine with api/access key authentication, but some require a sessionKey.
     * Load in these values securely, then call SystemUtils::setSessionKey to authenticate and store a session key.
     */
    public function setSessionCredentials(string $sessionEmailId, string $password, string $firmId): void
    {
        $this->sessionEmailId = $sessionEmailId;
        $this->sessionPassword = $password;
        $this->sessionFirmId = $firmId;
        SystemUtils::setSessionKey($this);
    }
    
    /* ObjectCrud 
     * 
     */
    public function read(string $appNameOrId, string $objectId): object 
    {
        return \ToddMinerTech\ApptivoPhp\ObjectCrud::read($appNameOrId, $objectId, $this);
    }
    
    /* ObjectDataUtils 
     * 
     */    
    public function getConfigData(string $appNameOrId): object
    {
        return \ToddMinerTech\ApptivoPhp\ObjectDataUtils::getConfigData($appNameOrId, $this);
    }
    
    public function getAttrDetailsFromLabel(array $fieldLabel, object $inputObj, string $appNameOrId): ?object 
    {
        $configData = $this->getConfigData($appNameOrId);
        return \ToddMinerTech\ApptivoPhp\ObjectDataUtils::getAttrDetailsFromLabel($fieldLabel, $inputObj, $configData);
    }
    
    public function getAttrSettingsObjectFromLabel(array $fieldLabel, string $appNameOrId): ?object 
    {
        $configData = $this->getConfigData($appNameOrId);
        return \ToddMinerTech\ApptivoPhp\ObjectDataUtils::getSettingsAttrObjectFromLabel($fieldLabel, $configData);
    }
    
    public function createNewAttrObjFromLabelAndValue(array $fieldLabel, array $newValue, string $appNameOrId): object 
    {
        $configData = $this->getConfigData($appNameOrId);
        return \ToddMinerTech\ApptivoPhp\ObjectDataUtils::createNewAttrObjFromLabelAndValue($fieldLabel, $newValue, $configData);
    }
    
    public function setAssociatedFieldValues(string $tagName, string $newValue, object &$object, string $appNameOrId): void
    {
        $configData = $this->getConfigData($appNameOrId);
        \ToddMinerTech\ApptivoPhp\ObjectDataUtils::setAssociatedFieldValues($tagName, $newValue, $object, $appNameOrId, $configData, $this);
    }
    public static function getAddressValueFromTypeAndField(string $addressType, string $addressFieldName, object $sourceModelObj): string
    {
        return \ToddMinerTech\ApptivoPhp\ObjectDataUtils::getAddressValueFromTypeAndField($addressType, $addressFieldName, $sourceModelObj);
    }
    
    /* SearchUtils 
     * 
     */  
    public function getAllBySearchText(string $searchText, string $appNameOrId): array
    {
        return \ToddMinerTech\ApptivoPhp\SearchUtils::getAllBySearchText($searchText, $appNameOrId, $this);
    }
    
    public function getEmployeeIdFromName(string $employeeNameToFind): string
    {
        return \ToddMinerTech\ApptivoPhp\SearchUtils::getEmployeeIdFromName($employeeNameToFind, $this);
    }
    
    public function getCustomerObjFromName(string $customerNameToFind): object
    {
        return \ToddMinerTech\ApptivoPhp\SearchUtils::getCustomerObjFromName($customerNameToFind, $this);
    }
    
    public function getCustomerIdFromName(string $customerNameToFind): string
    {
        return \ToddMinerTech\ApptivoPhp\SearchUtils::getCustomerIdFromName($customerNameToFind, $this);
    }
    public static function getAllRecordsInApp(string $appNameOrId,  ApptivoController $aApi, int $maxRecords = 20000): array
    {
        return \ToddMinerTech\ApptivoPhp\SearchUtils::getAllRecordsInApp($appNameOrId,  $aApi, $maxRecords);
    }
    
    /* Get/Set 
     * 
     */  
    public function getApiKey(): string
    {
        return $this->apiKey;
    }
    public function getAccessKey(): string
    {
        return $this->accessKey;
    }
    public function getUserNameStr(): string
    {
        return $this->apiUserNameStr;
    }
    public function getConfigDataArr(): array
    {
        return $this->configDataArr;
    }
    public function setConfigDataArr(array $newConfigDataArr): void
    {
        $this->configDataArr = $newConfigDataArr;
    }
}
