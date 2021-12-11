<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\DataUtils\StringUtil;
use ToddMinerTech\DataUtils\ArrUtil;
use ToddMinerTech\ApptivoPhp\ResultObject;
use ToddMinerTech\ApptivoPhp\Exceptions;

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
    private $object = null;
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
     * @return ResultObject We will update $this->object as a result, but also return a status.  No payload on success.
     */
    public function setAttributeValue(array $fieldLabel, array $newValue): ResultObject
    {
        //Otherwise set the value by detecting if this is a standard or custom attribute first
        $settingsAttrObjResult = $this->aApi->getAttrSettingsObjectFromLabel($fieldLabel, $this->appNameOrId);
        if(!$settingsAttrObjResult->isSuccessful) {
            return ResultObject::fail($settingsAttrObjResult->payload);
        }
        $settingsAttrObj = $settingsAttrObjResult->payload;
        if(isset($settingsAttrObj->tagName)) {
            $tagName = $settingsAttrObj->tagName;
        }
        if($settingsAttrObj->type == 'Standard') {
            //IMPROVEMENT Need to add proper support for multi select standard attributes like we support for custom attributes below.  For now we only support 1 input value for standard.
            //IMPROVEMENT Consider some method to take care of associated fields.  For example assigneeObjectRefName/RefId, or StatusName and StatusId
            if(count($newValue) > 1) {
                return ResultObject::fail('ApptivoPHP: CreateUtil: setAttributeValue: More than 1 value provided for a standard attribute.  Only single values are accepted for standard attributes right now.  $fieldLabel ( '.json_encode($fieldLabel).' )   $newValue ( '.json_encode($newValue).' )');
            }
            $this->aApi->setAssociatedFieldValues($tagName, $newValue[0], $this->object, $this->appNameOrId);

            if(isset($settingsAttrObj->validateType) && $settingsAttrObj->validateType == 'date') {
                //We expect m/d/Y but will attempt to convert any other formats now
                $convTime = strtotime($newValue[0]);
                $convDate = date('m/d/Y',$convTime);
                $valueToSet = $convDate;
            }else{
                $valueToSet = $newValue[0];
            }
            $this->object->$tagName = $valueToSet;
        }else{
            $newAttrObjResult = $this->aApi->createNewAttrObjFromLabelAndValue($fieldLabel, $newValue, $this->appNameOrId);
            if(!$newAttrObjResult->isSuccessful) {
                return $newAttrObjResult;
            }
            $newAttrObj = $newAttrObjResult->payload;
            $this->object->customAttributes[] = $newAttrObj;
        }
        return ResultObject::success();
    }
    /**
     * createObject
     * 
     * Perform the API save for an object in Apptivo
     *
     * @return ResultObject
     */
    public function createObject(): ResultObject
    {
        //Here we perform any validations before creation.
        //IMPROVEMENT Add more validation and extract to a more modular app-by-app solution
        switch ($this->appNameOrId) {
            case 'customers':
                if(
                    !isset($this->object->assigneeObjectRefName) || !$this->object->assigneeObjectRefName ||
                    !isset($this->object->assigneeObjectRefId) || !$this->object->assigneeObjectRefId ||
                    !isset($this->object->assigneeObjectId) || !$this->object->assigneeObjectId
                ) {
                    return ResultObject::fail('ApptivoPhp: CreateUtil: createObject: Customer object is missing a required value. $this->object->assigneeObjectRefName ('.$this->object->assigneeObjectRefName.')  $this->object->assigneeObjectRefId ('.$this->object->assigneeObjectRefId.') $this->object->assigneeObjectId ('.$this->object->assigneeObjectId.')');
                }
                break;
        }
        return ObjectCrud::create($this->appNameOrId, $this->object, $this->aApi);
    }
    
    /**
     * setAssociatedFieldValues
     * 
     * Wrapper function for ObjectDataUtils to automatically add to our created object
     *
     * @return void Returns the decoded json response from the Apptivo API
     */
    public function setAssociatedFieldValues(string $tagName, string $newValue): void
    {
        $currentObject = $this->object;
        $this->aApi->setAssociatedFieldValues($tagName, $newValue, $currentObject, $this->appNameOrId);
        $this->object = $currentObject;
    }
    
    /*
     * Get/Set
     */
    public function getObject(): object
    {
        return $this->object;
    }  
    public function setObject(object $inputObject): void
    {
        $this->object = $inputObject;
    }
            
}
