<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use Google\Cloud\Logging\LoggingClient;
use Illuminate\Support\Facades\Log;
use ToddMinerTech\DataUtils\StringUtil;
use ToddMinerTech\DataUtils\ArrUtil;

/**
 * Class CreateUtil
 *
 * Class to help manage the process of creating new records in Apptivo.
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class CreateUtil
{
    /**  @var ApptivoController $aApi The Miner Tech Apptivo package to interact with the Apptivo API */
    private $aApi; 
    /**  @var string $appNameOrId The Apptivo app name or id of this object */
    public $appNameOrId = null;
    /**  @var object $object The Apptivo API object to be created */
    public $object = null;
    /**  @var int $tableAttrIndex Index value within customAttributes that locates the targeted custom attribute table */
    public $tableAttrIndex = null;
    /**  @var array $tableRowsArr The array of rows in the targeted custom attributes table */
    public $tableRowsArr = [];
    /**  @var object $tableRowObj Current row being processed within a custom table */
    public $tableRowObj = null;
    /**  @var int $tableRowIndex Index value of the row within the custom attribute table */
    public $tableRowIndex = null;
    /**  @var object $tableColObj Current column being processed */
    public $tableColObj = null;
    /**  @var int $tableColIndex Index value of the column within the table row */
    public $tableColIndex = null;
    
    function __construct(string $appNameOrId, ApptivoController $aApi)
    {
        $this->appNameOrId = $appNameOrId;
        $this->object = new \stdClass();
        $this->aApi = $aApi;
    }
    
    /**
     * setAttributeValue
     * 
     * Takes a field label and a value, then updates the object with the appropriate standard/custom attribute data
     * 
     * @param array $inputLabel The attribute label as configured in Apptivo.  For table attributes the inputLabel should be an array: ["Table Section Name","Attribute Name"], otherwise a single member array.
     * 
     * @param array $newValue The value(s) you want to set.  Provide a single value array for for single fields, or multiple if attributeValues should be verified.
     *
     * @return void We will just update $this->object as a result
     */
    public function setAttributeValue(array $fieldLabel, array $newValue): void
    {
        $attrDetails = $this->aApi->getAttrDetailsFromLabel($fieldLabel, $this->object, $this->appNameOrId);
        if(isset($attrDetails->settingsAttrObj->tagName)) {
            $tagName = $attrDetails->settingsAttrObj->tagName;
        }
        if($attrDetails->settingsAttrObj->type == 'Standard') {
            $this->object->$tagName = $attrDetails->attrValue;
        }else{
            $newAttrObj = $this->aApi->createNewAttrObjFromLabelAndValue($fieldLabel, $newValue, $this->appNameOrId);
            $this->object->customAttributes[] = $newAttrObj;
        }
    }
    /**
     * createObject
     * 
     * Perform the API save for an object in Apptivo
     *
     * @return object Returns the decoded json response from the Apptivo API
     */
    public function createObject(): object
    {
        return ObjectCrud::create($this->appNameOrId, $this->object, $this->aApi);
    }
            
}
