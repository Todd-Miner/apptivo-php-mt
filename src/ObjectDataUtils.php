<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use Exception;
use Google\Cloud\Logging\LoggingClient;
use Illuminate\Support\Facades\Log;
use ToddMinerTech\ApptivoPhp\ApptivoController;
use ToddMinerTech\ApptivoPhp\SearchUtils;
use ToddMinerTech\DataUtils\StringUtil;

/**
 * Class ObjectDataUtils
 *
 * Class to perform processing tasks related to object record such as retreiving or updating a specific field value.  Designed to be called statically from ApptivoController
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class ObjectDataUtils
{
    /**
     * getConfigData
     *
     * @param string $appNameOrId App name, app id, or combo string for extended apps (cases-993829).
     * 
     * @param ApptivoController $aApi Your Apptivo controller object
     *
     * @return object Returns object containing the configuration for the app, or null if unable to locate
     */
    public static function getConfigData(string $appNameOrId, ApptivoController $aApi): object
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
        $currentConfigDataArr = $aApi->getConfigDataArr();
        foreach($currentConfigDataArr as $cConfig) {
            if($cConfig->appId == $appId) {
                $existingConfigData = $cConfig->configData;
                return $existingConfigData;
            }
        }
        $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.$appParams->objectUrlName.'?a=getConfigData&objectId='.$appId.'&apiKey='.$aApi->getApiKey().'&accessKey='.$aApi->getAccessKey().$aApi->getUserNameStr();
        $client = new \GuzzleHttp\Client();
        sleep($aApi->apiSleepTime);
        $res = $client->request('GET', $apiUrl);
        $body = $res->getBody();
        $bodyContents = $body->getContents();
        $newConfigData = json_decode($bodyContents);
        if($newConfigData) {
            $newConfigObj = new \stdClass();
            $newConfigObj->appId = $appId;
            $newConfigObj->appName = $appParams->appName;
            $newConfigObj->configData = $newConfigData;
            $currentConfigDataArr[] = $newConfigObj;
            $aApi->setConfigDataArr($currentConfigDataArr);
            return $newConfigData;
        }
        return null;
    }
    
    /**
     * getAttrDetailsFromLabel
     *
     * @param array $inputLabel The attribute label as configured in Apptivo.  For table attributes the inputLabel should be an array: ["Table Section Name","Attribute Name"], otherwise a single member array.
     *
     * @param object $inputObj The apptivo object containing the attribute we are searching for
     *
     * @param object $appConfig The apptivo app config as provided from the apptivo controller data
     *
     * @return object Returns a stdClass object with 3 attributes: 
     *      1 attrObj - The complete custom attribute object
     *      2 attrValue - The simple text value of the attribute.  Comma delimited if multiple values present.
     *      3 attrIndex - The 0 based index of the attribute within the customAttributes array of $inputObj
     *      4 settingsAttrObj - Complete custom attribute object from settings.  Contains the type, value ids, etc.
     */
    public static function getAttrDetailsFromLabel(array $inputLabel, object $inputObj, object $appConfig): ?object
    {
        if(!$inputObj || !isset($inputObj->customAttributes) || !$inputObj->customAttributes) {
            throw new Exception('ApptivoPhP: getAttrDetailsFromLabel: Invalid $inputObj provided.  Object is null, missing, or has empty customAttributes.');
        }
        $settingsAttrObj = self::getSettingsAttrObjectFromLabel($inputLabel, $appConfig);
        //Now check if the attribute id exists in this object 
        //IMPROVEMENT extract this into a real object since it got more complicated than expected
        $attributeDetails = new \stdClass();
        $attributeDetails->attrObj = null;
        $attributeDetails->attrValue = '';
        $attributeDetails->attrIndex = -1;
        $attributeDetails->settingsAttrObj = $settingsAttrObj;
        if($settingsAttrObj->type == 'Standard') {
            $tagName = $settingsAttrObj->tagName;
            $attributeDetails->attrValue = $inputObj->$tagName;
        }else{
            for($i = 0; $i < count($inputObj->customAttributes); $i++) {
                if($inputObj->customAttributes[$i]->customAttributeId == $settingsAttrObj->attributeId) {
                    $attributeDetails->attrObj = $inputObj->customAttributes[$i];
                    $attributeDetails->attrIndex = $i;
                    switch($settingsAttrObj->attributeTag) {
                        case 'multiSelect':
                        case 'check':
                            if(isset($inputObj->customAttributes[$i]->attributeValues)) {
                                //IMPROVEMENT - finish adding code to parse into comma delimited list of text values.
                                $attributeDetails->attrValue = json_encode($inputObj->customAttributes[$i]->attributeValues);
                            }
                            return $attributeDetails;
                        break;
                        case 'currency':
                        case 'date':
                        case 'input':
                        case 'link':
                        case 'number':
                        case 'reference':
                        case 'referenceField':
                        case 'select':
                        case 'textarea':
                            if(isset($inputObj->customAttributes[$i]->customAttributeValue)) {
                                $attributeDetails->attrValue = $inputObj->customAttributes[$i]->customAttributeValue;
                            }
                            return $attributeDetails;
                        break;
                        default:
                            throw new Exception('This attribute was found but the $settingsAttrObj->attributeTag ('.$settingsAttrObj->attributeTag.') is not yet supported for inputLabel ('.json_encode($inputLabel).')');
                    }
                }
            }
        }
        log::Debug('We didn\'t find the attribute label ('.json_encode($inputLabel).') present within this object.');
        return $attributeDetails;
    }
    
    /**
     * getSettingsAttrObjectFromLabel
     *
     * @param array $inputLabel The attribute label as configured in Apptivo.  For table attributes the inputLabel should be an array: ["Table Section Name","Attribute Name"], otherwise a single member array.
     *
     * @param object $inputObj The apptivo object containing the attribute we are searching for
     *
     * @param object $appConfig The apptivo app config as provided from the apptivo controller data
     *
     * @return object Returns the complete custom attribute object for the requested label.
     */
    public static function getSettingsAttrObjectFromLabel(array $inputLabel, object $appConfig): object
    {
        if(!$appConfig) {
            throw new Exception('getSettingsAttrObjectFromLabel: no valid config provided.');
        }
        
        $webLayout = $appConfig->webLayout;
        $sectionsNode = json_decode($webLayout);
        $sections = $sectionsNode->sections;
        
        //We expect either 1 or 2 array values for inputLabel.  Verify and set a flag to be used for locating the attribute below.
        if(count($inputLabel) == 0) {
            throw new Exception('getSettingsAttrObjectFromLabel: Function called with no values in $inputLabel.');
        } else if (count($inputLabel) == 1) {
            $isTableAttr = false;
        } else if (count($inputLabel) == 2) {
            $isTableAttr = true;
        } else {
            throw new Exception('getSettingsAttrObjectFromLabel: Function called with ('.count($inputLabel).') values in $inputLabel, but either 1 or 2 values are expected.  Contents of $inputLabel: '.json_encode($inputLabel));
        }

        foreach($sections as $cSection) {
            $sectionName = $cSection->label;
            $sectionAttributes = $cSection->attributes;

            //Proceed if we are checking all attributes, or if if its an array then we only proceed for a table that matches our label
            if( !$isTableAttr || ( $inputLabel && StringUtil::sComp($cSection->label,$inputLabel[0]) ) ) {
                foreach($sectionAttributes as $cAttr) {
                    if(!$cAttr->label) {
                        continue;
                    }
                    if(isset($cAttr->label->modifiedLabel)) {
                        $labelName = $cAttr->label->modifiedLabel;
                    }else{
                        if(isset($cAttr->modifiedLabel)) {
                            $labelName = $cAttr->modifiedLabel;
                        }else{
                            throw new Exception('objectDataUtils: getAttrValueFromLabel: unable to locate the modifiedLabel attribute of this cAttr json ('.json_encode($cAttr));
                        }
                    }
                    $attributeType = $cAttr->type;
                    if(!isset($cAttr->attributeTag) || $cAttr->attributeTag == null) {
                        $attributeTag = $cAttr->right[0]->tag;
                    }else{
                        $attributeTag = $cAttr->attributeTag;
                    }
                    if(!isset($cAttr->tagName) || $cAttr->tagName == null) {
                        $attributeTagName = $cAttr->right[0]->tagName;
                    }else{
                        $attributeTagName = $cAttr->tagName;
                    }
                    $attributeId = $cAttr->attributeId;
                    $selectedValues = [];
                    //This is a potential attribute.  Now let's find the attribute with the matching label.  Both conditions for regular attribute and attribute in table
                    if( $cAttr->isEnabled && 
                    ( 
                        (!$isTableAttr && StringUtil::sComp($attributeTagName,$inputLabel[0])) || 
                        (!$isTableAttr && StringUtil::sComp($labelName,$inputLabel[0])) || 
                        ($isTableAttr && StringUtil::sComp($labelName,$inputLabel[1])) 
                    )
                    ) {
                        //We have matched the right attribute from settings.  Now match value if it's a dropdown or multi select.
                        return $cAttr;
                    }
                }	
            }
        }
        Throw new Exception('getSettingsAttrObjectFromLabel: We could not locate this attribute inputLabel ('.json_encode($inputLabel).') within the settings');
    }
    
    /**
     * createNewAttrObjFromLabelAndValue
     *
     * @param array $inputLabel The attribute label as configured in Apptivo.  For table attributes the inputLabel should be an array: ["Table Section Name","Attribute Name"], otherwise a single member array.
     *
     * @param object $inputObj The apptivo object containing the attribute we are searching for
     *
     * @param object $appConfig The apptivo app config as provided from the apptivo controller data
     *
     * @return object Returns a newly generated attribute to insert or replace an existing attribute within the customAttributes array of an object
     */
    public static function createNewAttrObjFromLabelAndValue(array $inputLabel, array $inputValue, object $appConfig): object
    {
        $settingsAttrObj = self::getSettingsAttrObjectFromLabel($inputLabel, $appConfig);

        if(!isset($settingsAttrObj->attributeTag) || $settingsAttrObj->attributeTag == null) {
                $attributeTag = $settingsAttrObj->right[0]->tag;
        }else{
                $attributeTag = $settingsAttrObj->attributeTag;
        }
        if(!isset($settingsAttrObj->tagName) || $settingsAttrObj->tagName == null) {
                $attributeTagName = $settingsAttrObj->right[0]->tagName;
        }else{
                $attributeTagName = $settingsAttrObj->tagName;
        }
        if($attributeTag == 'select' || $attributeTag == 'multiSelect' || $attributeTag == 'check') {
            if( ($attributeTag == 'multiSelect' || $attributeTag == 'check') ) {
                $foundVal = false;
                if(isset($settingsAttrObj->optionValueList)) {
                    $optionList = $settingsAttrObj->optionValueList;
                }else{
                    $optionList = $settingsAttrObj->right[0]->optionValueList;
                }
                foreach($inputValue as $iVal) {
                    foreach($optionList as $cVal) {
                        //log::debug('comparing for field '.$labelName.'  ('.strip($cVal->optionObject).') vs ('.strip($iVal).')');
                        if($cVal->optionObject && is_string($iVal) && StringUtil::ssComp($cVal->optionObject,$iVal)) {
                            //log::debug('We have matched the right value for this attribute.  Save them here, and we\'ll define below.');
                            $selectedValues[] = $cVal;
                            $foundVal = true;
                            break;
                        }
                    }
                }
            }else{
                $foundVal = false;
                if(isset($settingsAttrObj->optionValueList)) {
                    $optionParent = $settingsAttrObj;
                    $optionList = $settingsAttrObj->optionValueList;
                }else{
                    $optionParent = $settingsAttrObj->right[0];
                    if(isset($settingsAttrObj->right[0]->optionValueList)) {
                        $optionList = $settingsAttrObj->right[0]->optionValueList;
                    }else{
                        $optionList = $settingsAttrObj->right[0]->options;
                    }
                }
                for($valC=0;$valC<count($optionList);$valC++){
                    //log::debug('comparing for field '.$labelName.'  ('.strip($cVal->optionObject).') vs ('.strip($inputValue[0]).')');
                    if(is_object($optionList[$valC])) {
                        //log::debug('comparing $optionList[$valC]->optionObject ('.$optionList[$valC]->optionObject.')  vs  $inputValue[0] ('.$inputValue[0].')');
                        if(StringUtil::ssComp($optionList[$valC]->optionObject,$inputValue[0])) {
                            //We have matched the right value for this attribute.  Save them here, and we'll define below.
                            $selectedValue = $optionList[$valC]->optionObject;
                            $selectedValueId = $optionList[$valC]->optionId; 
                            //If the values are the same, return the tagId instead
                            if($selectedValue == $selectedValueId) {
                                $selectedValueId = $optionParent->tagId;
                            }
                            $foundVal = true;
                            break;
                        }
                        if($optionParent->options && $optionParent->options[$valC] && StringUtil::ssComp($optionParent->options[$valC],$inputValue[0]) ) {
                            //We have matched the right value for this attribute.  Save them here, and we'll define below.
                            $selectedValue = $optionParent->options[$valC];
                            //return the tagId instead
                            $selectedValueId = $optionParent->tagId;


                            log::debug('We matched the value based on options list, not optionObject.   selectedValue ('.$selectedValue.')  selectedValueId ('.$selectedValueId.')');
                            $foundVal = true;
                            break;
                        }
                    }else{
                        //log::debug('Single val toggle detected $optionParent: '.json_encode($optionParent));
                        //For single val toggles we just have a text value like yes.  Assume it's single value and assign this value + tagId.
                        $selectedValue = $optionParent->options[0];
                        $selectedValueId = $optionParent->tagId;
                        $foundVal = true;
                        break;
                    }
                }
                if(!$foundVal && $inputValue) {
                    //If we end the loop without a value match we must log an exception, unless passed in value was empty
                    Throw new Exception('Could not find a matching value for the attribute label ('.json_encode($inputLabel).')  and value ('.json_encode($inputValue).')');
                    //log::debug('cAttr for failed label match:  '.json_encode($settingsAttrObj));
                }
            }
        }

        //log::debug('Proceeding to set this attr.  $selectedValue ('.$selectedValue.')   and selectedValueId ('.$selectedValueId.')');
        //Now let's build our complete object for this attribute based on type and the matched attribute from settings	
        $newAttr = new \stdClass;
        $newAttr->customAttributeType = $attributeTag;
        $newAttr->customAttributeId = $settingsAttrObj->attributeId;
        if(isset($settingsAttrObj->tagName)) {
            $newAttr->customAttributeName = $settingsAttrObj->tagName;
            $newAttr->customAttributeTagName = $settingsAttrObj->tagName;
        }else{
            $newAttr->customAttributeName = $settingsAttrObj->right[0]->tagName;
            $newAttr->customAtJObjecttributeTagName = $settingsAttrObj->right[0]->tagName;
        }
        switch($attributeTag) {
            case 'check':
                $newAttr->customAttributeValue = '';
                $newAttr->fieldType = 'NUMBER';
                $newAttr->attributeValues = [];
                //Detect if we set an array of values or a single value
                if(count($selectedValues) > 0) {
                    foreach($selectedValues as $cVal) {
                        $valueObj = new stdClass;
                        $valueObj->attributeId = $cVal->optionId;
                        $valueObj->attributeValue =  $cVal->optionObject;
                        //$valueObj->shape =  '';
                        //$valueObj->color =  '';
                        $newAttr->attributeValues[] = $valueObj;	
                        unset($valueObj);
                    }
                }else{
                    $valueObj = new stdClass;
                    $valueObj->attributeId = $selectedValueId;
                    $valueObj->attributeValue = $selectedValue;
                    //$valueObj->shape =  ''; //suspected they don't do anything, not sure exact conditions where these are present in Apptivo queries
                    //$valueObj->color =  '';
                    $newAttr->attributeValues[] = $valueObj;
                }
            break;
            case 'counter':
                //Expected to be 'Auto generated number'
                $newAttr->customAttributeValue = $inputValue[0];
            break;
            case 'currency':
                if($inputValue) {
                    $newAttr->customAttributeValue = $inputValue[0];
                    $newAttr->fieldType = 'NUMBER';
                    $newAttr->currencyCode = 'USD'; //hard-coded for now
                }else{
                    $newAttr->customAttributeValue = null;
                    $newAttr->customAttributeValueId = '';
                }
            break;
            case 'date':
                $newAttr->customAttributeValue = $inputValue[0];
                if($inputValue) {
                    //Assuming inputval is m/d/Y, convert to Y-m-d 
                    //$newAttr->dateValue = date('Y-m-d',strtotime($inputValue[0])).' 00:00:00';
                    $newAttr->fieldType = 'NUMBER';
                    $newAttr->attributeValues = [];
                }else{
                    $newAttr->customAttributeValueId = '';
                }
            break;
            case 'input':
                $newAttr->customAttributeValue = $inputValue[0];
                $newAttr->fieldType = 'NUMBER';
            break;
            case 'multiSelect':
                $newAttr->customAttributeValue = '';
                $newAttr->fieldType = 'NUMBER';
                $newAttr->attributeValues = [];
                //Detect if we set an array of values or a single value
                if(count($selectedValues) > 0) {
                    foreach($selectedValues as $cVal) {
                        $valueObj = new stdClass;
                        $valueObj->attributeId = $cVal->optionId;
                        $valueObj->attributeValue =  $cVal->optionObject;
                        $newAttr->attributeValues[] = $valueObj;	
                        unset($valueObj);
                    }
                }else{
                    $valueObj = new stdClass;
                    $valueObj->attributeId = $selectedValueId;
                    $valueObj->attributeValue = $selectedValue;
                    $newAttr->attributeValues[] = $valueObj;
                }
            break;
            case 'number':
                $newAttr->customAttributeValue = $inputValue[0];
                $newAttr->numberValue = $inputValue[0];
                $newAttr->fieldType = 'NUMBER';
            break;
            case 'reference':
                //Reference attributes take an array of objectId, objectRefId, objectRefName
                if($inputValue) {
                    $newAttr->customAttributeValue = $inputValue[2];
                    //If the object id is more than 3 digits then it's a custom app.  We're going to assume this is a cases app extension for now.  Need to refactor later and allow passing in app name and object ID.
                    if(strlen($inputValue[0]) > 3) {
                        //This is for custom apps
                        $newAttr->id = $inputValue[1];
                    }else{
                        if($inputValue[0] == '3') {
                            //This is for customers, need support for others
                            $newAttr->customerId = $inputValue[1];
                            $newAttr->customerName = $inputValue[2];
                        }elseif($inputValue[0] == '2') {
                            //This is for contacts, need support for others
                            $newAttr->contactId = $inputValue[1];
                            $newAttr->fullName = $inputValue[2];
                        }
                    }
                    $newAttr->customAttributeValueId = '';
                    $newAttr->updateAutoComplete = true;
                    $newAttr->attributeValues = [];
                    $newAttr->objectId = $inputValue[0];
                    $newAttr->objectRefId = $inputValue[1];
                    $newAttr->objectRefName = $inputValue[2];
                    $newAttr->name = $inputValue[2];
                }else{
                    $newAttr->customAttributeValue = '';
                    $newAttr->customAttributeValueId = '';
                }
            break;
            case 'referenceField':
                //ReferenceField attributes take an array of objectId, objectRefId, value
                //log::debug('matchedAttr: '.json_encode($matchedAttr));
                $newAttr->customAttributeValue = $inputValue[2];
                $newAttr->fieldType = $matchedAttr->associatedField->referenceAttributeTag;
                //Special exception for phoneEmail, they appear as select for some reason in config
                if($matchedAttr->associatedField->referenceTagName == 'emailType' || $matchedAttr->associatedField->referenceTagName == 'phoneType') {
                    $newAttr->fieldType = 'phoneEmail';
                    $newAttr->customAttributeValue1 = $inputValue[2];
                    $newAttr->customAttributeValue2 = $matchedAttr->associatedField->referenceTypeCode;
                }else{
                    //phoneEmail doesn't need these for some reason
                    $newAttr->refFieldObjectRefName = $inputValue[1];
                    $newAttr->refFieldObjectId = $inputValue[0];
                }
                $newAttr->attributeId = $matchedAttr->associatedField->referenceAttributeId;
                $newAttr->objectId = intVal($inputValue[0]);
                $newAttr->objectRefId = intVal($inputValue[1]);
                if($newAttr->fieldType == 'select') {
                    $targetAppConfig = self::getConfigById($inputValue[0]);
                    //log::debug('Just got targetAppConfig of:  '.json_encode($targetAppConfig));
                    $targetAppAttr = self::getAttrValue($inputLabel,$inputValue[2],$targetAppConfig);
                    if($targetAppAttr->customAttributeValueId) {
                        $newAttr->customAttributeValueId = $targetAppAttr->customAttributeValueId;
                    }
                }else{
                    $newAttr->refFieldObjectRefId = $inputValue[1];
                }
            break;
            case 'select':
                if($inputValue) {
                    $newAttr->customAttributeValue = $selectedValue;
                    $newAttr->customAttributeValueId = $selectedValueId;
                    $newAttr->fieldType = 'NUMBER';
                    $newAttr->attributeValues = [];
                }else{
                    $newAttr->customAttributeValue = '';
                    $newAttr->customAttributeValueId = '';
                }
            break;
            case 'textarea':
                $newAttr->customAttributeValue = $inputValue[0];
                $newAttr->attributeValues = [];
            break;
        }
        if(!$newAttr) {
            Throw new Exception('ERROR: createNewAttrObjFromLabelAndValue was unable to produce an attribute');
        }
        return $newAttr;
    }
    
    /**
     * setAssociatedFieldValues
     * 
     * Used when updating standard attributes on an object.  Will check if we have an associated field for a provided tagName, then update that attribute on the provided object.
     * Example use case is passing in statusName as a tag within the customers app.  This function will detect the associated field statusId, then get the matching id from the config and update it.
     *
     * @param array $tagName The attribute tagName 
     *
     * @param string $newValue The value for the provided tagName, used to map to associated id numbers
     *
     * @param string $appNameOrId The apptivo app name or id used to find the right rules below
     *
     * @param object $configData The apptivo app config as provided from the apptivo controller data
     *
     * @param ApptivoController $aApi Your Apptivo controller object
     *
     * @return void We update the $object object directly
     */
    public static function setAssociatedFieldValues(string $tagName, string $newValue, object &$object, string $appNameOrId, object $configData, ApptivoController $aApi): void
    {
        //IMPROVEMENT - Refactor this to have a proper modular way to define rules per app.  Quick and dirty to establish a few apps first.
        switch(strtolower($appNameOrId)) {
            case 'customers':
                switch($tagName) {
                    case 'statusName':
                        $object->statusId = self::getCustomerStatusIdFromStatusName($newValue, $configData);
                    break;
                    case 'assigneeObjectRefName':
                        //IMPROVEMENT Hard-coded to an employee - Add support for team objects at some point
                        $object->assigneeObjectRefId = \ToddMinerTech\ApptivoPhp\SearchUtils::getEmployeeIdFromName($newValue, $aApi);
                        $object->assigneeObjectId = 8;
                    break;
                }
            break;
        }
    }
    
    /**
     * getCustomerStatusIdFromStatusName
     * 
     * Provide a statusName value and locate the matching statusId value from customers app settings
     *
     * @param string $statusNameToFind The valid statusName value
     *
     * @param object $configData The apptivo app config as provided from the apptivo controller data
     *
     * @return string Returns the matching ID or throws an exception
     */
    public static function getCustomerStatusIdFromStatusName(string $statusNameToFind, object $configData): string
    {
        foreach($configData->customerStatusList as $cVal) {
            if(StringUtil::sComp($statusNameToFind,$cVal->statusName)) {
                return (string)$cVal->statusId;
            }
        }
        throw new Exception('ApptivoPHP: ObjectDataUtils: getCustomerStatusIdFromStatusName: unable to lcoate a matching status for $statusNameToFind ('.$statusNameToFind.')');
    }
    
    /**
     * Get an address field value from an Apptivo object using an address type name and field name
     *
     * @param string $addressType The addressType value as configured in this Apptivo app
     *
     * @param string $addressFieldName The address object attribute name as it appears in Apptivo data
     *
     * @param object $sourceModelObj The Apptivo object to search
     *
     * @return string With address field value, or empty string if no value present
     */
    public static function getAddressValueFromTypeAndField(string $addressType, string $addressFieldName, object $sourceModelObj): string
    {
        $allAddresses = $sourceModelObj->addresses;
        for($a = 0; $a < count($allAddresses); $a++) {
            if(StringUtil::ssComp($addressType, $allAddresses[$a]->addressType)) {
                if(isset($allAddresses[$a]->$addressFieldName)) {
                    return $allAddresses[$a]->$addressFieldName;
                }
            }
        }
        return ''; //If we fail to match return an empty string
    }
    
    /**
     * getTableRowColIndexFromAttributeId
     * 
     * Provide a customAttributeId to get the index of the column where the attribute exists.  Columns are built based on order the attribute was added from within Apptivo, not consistent.
     *
     * @param string $customAttributeId customAttributeId for the attribute within the table
     *
     * @param object $tableRowObj The object data for the table row
       *
     * @return int The 0 based index of the column or null if the column doesn't exist in this row
     */
    function getTableRowColIndexFromAttributeId(string $customAttributeId, object $tableRowObj): ?int
    {
        //IMPROVEMENT get this extracted into a class of data utilities for tables
        for($col = 0; $col < count($tableRowObj->columns); $col++) {
            if(StringUtil::sComp($tableRowObj->columns[$col]->customAttributeId,$customAttributeId)) {
                return $col;
            }
        }
        return null;
    }
}