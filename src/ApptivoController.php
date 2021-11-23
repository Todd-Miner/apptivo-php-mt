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
    public static function getTableRowColIndexFromAttributeId(string $customAttributeId, object $tableRowObj): ?int
    {
        return \ToddMinerTech\ApptivoPhp\ObjectDataUtils::getTableRowColIndexFromAttributeId($customAttributeId, $tableRowObj);
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
