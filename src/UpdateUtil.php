<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use Exception;
use Google\Cloud\Logging\LoggingClient;
use Illuminate\Support\Facades\Log;
use ToddMinerTech\DataUtils\CountryStateUtil;
use ToddMinerTech\DataUtils\StringUtil;
use ToddMinerTech\DataUtils\ArrUtil;

/**
 * Class UpdateUtil
 *
 * Class to help manage the process of updating an Apptivo update.  Stores the object data and stores key attributes in a predictable way to be digested by the update method.
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class UpdateUtil
{
    /**  @var ApptivoController $aApi The Miner Tech Apptivo package to interact with the Apptivo API */
    private $aApi; 
    /**  @var string $appNameOrId The Apptivo app name or id of this object */
    public $appNameOrId = null;
    /**  @var object $object The Apptivo API object to be updated */
    public $object = null;
    /**  @var array $attributeIds List of attribute ids that have been changed */
    public $attributeIds = [];
    /**  @var array $attributeNames List of attribute names that have been changed */
    public $attributeNames = [];
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
    
    function __construct(string $appNameOrId, object $inputObject, ApptivoController $aApi)
    {
        $this->appNameOrId = $appNameOrId;
        $this->object = $inputObject;
        $this->aApi = $aApi;
    }
    
    /**
     * checkAndUpdateFieldWithValue
     * 
     * Takes a field label and value, then checks the current object to see if an update is required, and makes the change if so
     * 
     * @param array $inputLabel The attribute label as configured in Apptivo.  For table attributes the inputLabel should be an array: ["Table Section Name","Attribute Name"], otherwise a single member array.
     * 
     * @param array $newValue The value(s) you want to check and update.  Provide a single value array for for single fields, or multiple if attributeValues should be verified.
     *
     * @return void We will just update $this->object and the $this->attributeIds/Names if any change is needed
     */
    public function checkAndUpdateFieldWithValue(array $fieldLabel, array $newValue): void
    {
        $attrDetails = $this->aApi->getAttrDetailsFromLabel($fieldLabel, $this->object, $this->appNameOrId);
        if(isset($attrDetails->settingsAttrObj->tagName)) {
            $tagName = $attrDetails->settingsAttrObj->tagName;
        }
        $needsNewAttribute = false;
        if(!$attrDetails->attrObj) {
            log::debug('checkAndUpdateFieldWithValue: This value is not present yet, we need to create a new attribute object to insert into our object.');
            if($attrDetails->settingsAttrObj->type == 'Standard') {
                $this->attributeIds = ArrUtil::addArrIfNew($attrDetails->settingsAttrObj->attributeId, $this->attributeIds);
                $this->attributeNames = ArrUtil::addArrIfNew($tagName, $this->attributeNames);
                $this->object->$tagName = $attrDetails->attrValue;
            }else{
                $newAttrObj = $this->aApi->createNewAttrObjFromLabelAndValue($fieldLabel, $newValue, $this->appNameOrId);
                $this->object->customAttributes[] = $newAttrObj;
                $this->attributeIds = ArrUtil::addArrIfNew($newAttrObj->customAttributeId,$this->attributeIds);
                $this->attributeNames = ArrUtil::addArrIfNew('customAttributes',$this->attributeNames);
            }
        } else {
            //This attribute is present, now we check if it needs to be updated
            if($attrDetails->settingsAttrObj->type == 'Standard') {
                if(!StringUtil::sComp($attrDetails->attrValue, $newValue[0])) {
                    log::debug('checkAndUpdateFieldWithValue: Different value detected for single value field.  Will update complete attriubte.  Existing value: '.$attrDetails->attrValue.'    New Value: '.$newValue[0]);
                    if(isset($attrDetails->settingsAttrObj->addressAttributeId) && $attrDetails->settingsAttrObj->addressAttributeId) {
                        $this->attributeIds = ArrUtil::addArrIfNew($attrDetails->settingsAttrObj->addressAttributeId, $this->attributeIds);
                        $this->attributeNames = ArrUtil::addArrIfNew('address', $this->attributeNames);
                    }else{
                        $this->attributeIds = ArrUtil::addArrIfNew($attrDetails->settingsAttrObj->attributeId, $this->attributeIds);
                        $this->attributeNames = ArrUtil::addArrIfNew($tagName, $this->attributeNames);
                    }
                    //Special case for addresses.  We need to check each address and verify it has the right type.
                    //IMPROVEMENT add custom addres support, and clean up this splintered address implementation
                    if(strpos($fieldLabel[0], '||') !== false) {
                        $addrParts = explode('||', $fieldLabel[0]);
                        $addressType = $addrParts[1];
                        $updateComplete = false;
                        for($a = 0; $a < count($this->object->addresses); $a++) {
                            if(StringUtil::sComp($this->object->addresses[$a]->addressType,$addressType)) {
                                //If this is a state field, we must get stateCode too.
                                if($tagName == 'state') {
                                    $stateObj = CountryStateUtil::getStateNameOrCode($newValue[0]);
                                    $this->object->addresses[$a]->state = $stateObj->name;
                                    $this->object->addresses[$a]->stateCode = $stateObj->code;
                                }else{
                                    $this->object->addresses[$a]->$tagName = $newValue[0];
                                }
                                $updateComplete = true;
                                break;
                            }
                        }
                        if(!$updateComplete) {
                            throw new Exception('checkAndUpdateFieldWithValue:  Could not locate an address with the proper type. $fieldLabel:  '.json_encode($fieldLabel));
                        }
                    }else{
                        //"Standard" Standard attributes
                        $this->object->$tagName = $newValue[0];
                    }
                }
            }else{
                if(in_array($attrDetails->attrObj->customAttributeType, ['check', 'multiSelect'])) {
                    if(!isset($attrDetails->attrObj->attributeValues)) {
                        throw new Exception('checkAndUpdateFieldWithValue: $newValue was provided with ('.count($newValue) .') values but $attrDetails->attrObj->attributeValues was not set as expected.  $attrDetails: '.json_encode($attrDetails));
                    }
                    for($i = 0; $i < count($attrDetails->attrObj->attributeValues); $i++) {
                        if(!StringUtil::sComp($attrDetails->attrObj->attributeValues[$i],$newValue[$i])) {
                            log::debug('checkAndUpdateFieldWithValue: Different values detected for multi select field.  Will update complete attriubte.  Existing values: '.json_encode($attrDetails->attrObj->attributeValues).'    New Values: '.json_encode($newValue));
                            $newAttrObj = $this->aApi->createNewAttrObjFromLabelAndValue($fieldLabel, $newValue, $this->appNameOrId);
                            $this->object->customAttributes[$attrDetails->attrIndex] = $newAttrObj;
                            $this->attributeIds = ArrUtil::addArrIfNew($newAttrObj->customAttributeId,$this->attributeIds);
                            $this->attributeNames = ArrUtil::addArrIfNew('customAttributes',$this->attributeNames);
                            break;
                        }
                    }
                }else{
                    if(!StringUtil::sComp($attrDetails->attrValue, $newValue[0])) {
                        log::debug('checkAndUpdateFieldWithValue: Different value detected for single value field.  Will update complete attriubte.  Existing value: '.$attrDetails->attrValue.'    New Value: '.$newValue[0]);
                        $newAttrObj = $this->aApi->createNewAttrObjFromLabelAndValue($fieldLabel, $newValue, $this->appNameOrId);
                        $this->object->customAttributes[$attrDetails->attrIndex] = $newAttrObj;
                        $this->attributeIds = ArrUtil::addArrIfNew($newAttrObj->customAttributeId,$this->attributeIds);
                        $this->attributeNames = ArrUtil::addArrIfNew('customAttributes',$this->attributeNames);
                    }
                }
            }
        }
    }
    /**
     * updateObject
     * 
     * Perform the API update for an object in Apptivo if any attributeIds are flagged
     *
     * @return object Returns the decoded json response from the Apptivo API
     */
    public function updateObject(): object
    {
        if(count($this->attributeIds) > 0 || count($this->attributeNames) > 0) {
            $isCustomAttributeUpdate = false;
            if(in_array('customAttributes',$this->attributeNames)) {
                $isCustomAttributeUpdate = true;
            }
            $isAddressUpdate = false;
            if(in_array('address',$this->attributeNames)) {
                $isAddressUpdate = true;
            }
            return ObjectCrud::update($this->appNameOrId, $this->attributeNames, $this->attributeIds, $this->object, $isCustomAttributeUpdate, $isAddressUpdate, $this->aApi);
        }else{
            return $this->object;
        }
    }
            
}
