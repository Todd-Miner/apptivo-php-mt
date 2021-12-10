<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\ApptivoPhp\ApptivoController;
use ToddMinerTech\ApptivoPhp\ObjectDataUtils;
use ToddMinerTech\ApptivoPhp\ResultObject;
use ToddMinerTech\ApptivoPhp\SearchUtils;
use ToddMinerTech\DataUtils\StringUtil;

/**
 * Class ObjectTableUtils
 *
 * Class to help retrieve and manipulate data from tables within Apptivo objects.  Designed to be called statically from ApptivoController
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class ObjectTableUtils
{
    /**
     * getTableSectionRowsFromSectionLabel
     * 
     * Get the row data from a table section in and Apptivo object
     *
     * @param string $tableId The section attribute id for the table section
     *
     * @param object $objectData The Apptivo object data
     *
     * @param object $inputConfig The Apptivo app config data
       *
     * @return ResultObject Will return the table rows as an array
     */
    public static function getTableSectionRowsFromSectionLabel(string $tableSectionLabel, object $objectData, object $inputConfig): ResultObject
    {
        if(!$objectData || !isset($objectData->customAttributes)) {
            return ResultObject::fail('ApptivoPhp: ObjectTableUtils: getTableSectionRowsFromSectionLabel: $objectData provided was not valid with a customAttributes attribute.  $objectData:  '.json_encode($objectData));
        }
        $tableSectionIdResult = self::getTableSectionAttributeIdFromLabel($tableSectionLabel, $inputConfig);
        if(!$tableSectionIdResult->isSuccessful) {
            return ResultObject::fail($tableSectionIdResult->payload);
        }
        $tableSectionId = $tableSectionIdResult->payload;
        return self::getTableSectionRowsFromSectionId($tableSectionId, $objectData);
    }
    
    /**
     * getTableSectionAttributeIdFromLabel
     * 
     * Provide a customAttributeId to get the index of the column where the attribute exists.  Columns are built based on order the attribute was added from within Apptivo, not consistent.
     *
     * @param string $inputLabel The section attribute label for the table section
     *
     * @param object $inputConfig The Apptivo app config data
       *
     * @return ResultObject Will return the table section attribute id if found
     */
    public static function getTableSectionAttributeIdFromLabel(string $inputLabel, object $inputConfig): ResultObject
    {
        $webLayout = $inputConfig->webLayout;
        $sectionsNode = json_decode($webLayout);
        $sections = $sectionsNode->sections;
        $foundAttr = false;
        foreach($sections as $cSection) {
            $sectionName = $cSection->label;
            if(StringUtil::sComp($sectionName,$inputLabel)) {
                return ResultObject::success($cSection->id);
            }
        }
        return ResultObject::fail('ApptivoPhp: OBjectTableUtils: getTableSectionAttributeIdFromLabel: Could not find our attribute to get a value from, check label ('.$inputLabel.')',true);
    }
    
    /**
     * getTableSectionAttributeIndexFromLabel
     * 
     * Provide a customAttributeId to get the index of the column where the attribute exists.  Columns are built based on order the attribute was added from within Apptivo, not consistent.
     *
     * @param string $inputLabel The section attribute label for the table section
     *
     * @param object $inputConfig The Apptivo app config data
       *
     * @return ResultObject Will return the table section attribute index within customAttributes array
     */
    public static function getTableSectionAttributeIndexFromLabel(string $inputLabel, object $inputObject, object $inputConfig): ResultObject
    {
        $tableSectionAttrResult = self::getTableSectionAttributeIdFromLabel($inputLabel, $inputConfig);
        if(!$tableSectionAttrResult->isSuccessful) {
            return $tableSectionAttrResult;
        }
        for($i = 0; count($inputObject->customAttributes); $i++) {
            if(StringUtil::sComp($inputObject->customAttributes[$i]->customAttributeId, $tableSectionAttrResult->payload)) {
                return ResultObject::success($i);
            }
        }
        return ResultObject::fail('ApptivoPhp: OBjectTableUtils: getTableSectionAttributeIndexFromLabel: Could not find our attribute to get an index from $inputLabel ('.$inputLabel.')',true);
    }
    
    /**
     * getTableSectionRowsFromSectionId
     * 
     * Get the row data from a table section in and Apptivo object
     *
     * @param string $tableId The section attribute id for the table section
     *
     * @param object $objectData The Apptivo object data
       *
     * @return ResultObject Will return the table rows 
     */
    public static function getTableSectionRowsFromSectionId(string $tableSectionId, object $objectData): ResultObject
    {
        if(!$objectData || !isset($objectData->customAttributes)) {
            return ResultObject::fail('ApptivoPhp: ObjectDataUtils: getTableSectionRowsFromSectionId: $objectData provided was not valid with a customAttributes attribute.  $objectData:  '.json_encode($objectData));
        }
        foreach($objectData->customAttributes as $cAttr) {
            if($cAttr->customAttributeId == $tableSectionId) {
                return ResultObject::success($cAttr->rows);
            }
        }
        return ResultObject::fail('ApptivoPhp: ObjectTableUtils: getTableSectionRowsFromSectionId: Unable to locate the table section using $tableSectionId ('.$tableSectionId.') within the object $objectData:   '.json_encode($objectData));
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
     * @return ResultObject The 0 based index of the column or null if the column doesn't exist in this row
     */
    public static function getTableRowColIndexFromAttributeId(string $customAttributeId, object $tableRowObj): ResultObject
    {
        //IMPROVEMENT get this extracted into a class of data utilities for tables
        for($col = 0; $col < count($tableRowObj->columns); $col++) {
            if(StringUtil::sComp($tableRowObj->columns[$col]->customAttributeId,$customAttributeId)) {
                return ResultObject::success($col);
            }
        }
        return ResultObject::fail('ApptivoPhp: ObjectTableUtils: getTableRowColIndexFromAttributeId: Unable to locate this column  $customAttributeId ('.$customAttributeId.') within the object data.  This can be common when accessing attributes added after the last time an object was created.');
    }
    
    /**
     * getTableRowAttrValueFromLabel
     * 
     * Find the value of a custom attribute in a table row object
     * You can loop a table and call this function to get the values reliably since the column index is not consistent between records
     *
     * @param string $inputLabel custom attribute label
     *
     * @param object $inputRowObj The object data for the table row
     *
     * @param object $inputConfig The Apptivo app config
       *
     * @return ResultObject The value of the attribute
     */
    public static function getTableRowAttrValueFromLabel(string|array $inputLabel, object $inputRowObj, object $inputConfig): ResultObject
    {
        if(!$inputRowObj && $inputRowObj->columns) {
            return ResultObject::fail('ApptivoPhp: ObjectTableUtils: getTableRowAttrValueFromLabel: This table row had no columns availalbe.');
        }
        $customAttributeIdToFindResult = ObjectDataUtils::getAttributeIdFromLabel($inputLabel,$inputConfig);
        if(!$customAttributeIdToFindResult->isSuccessful) {
            return ResultObject::fail('ApptivoPhp: ObjectTableUtils: getTableRowAttrValueFromLabel: This table row had no columns availalbe.');
        }
        $customAttributeIdToFind = $customAttributeIdToFindResult->payload;
        for($col=0;$col<count($inputRowObj->columns);$col++) {
            if($inputRowObj->columns[$col]->customAttributeId == $customAttributeIdToFind) {
                //logIt('returning customAttributeValue ('.$inputRowObj->columns[$col]->customAttributeValue.') from this json: '.json_encode($inputRowObj->columns[$col]));
                if(isset($inputRowObj->columns[$col]->customAttributeValue) && $inputRowObj->columns[$col]->customAttributeValue) {
                    return ResultObject::success($inputRowObj->columns[$col]->customAttributeValue);
                }elseif(isset($inputRowObj->columns[$col]->attributeValues) && isset($inputRowObj->columns[$col]->attributeValues[0]) && isset($inputRowObj->columns[$col]->attributeValues[0]->attributeValue)) {
                    return ResultObject::success($inputRowObj->columns[$col]->attributeValues[0]->attributeValue);
                }
            }
        }
        //If we cannot locate this column then we provide an empty string
        return ResultObject::success('');
    }
    
    /**
     * getTableRowNoteAttributeObj
     * 
     *  Pass in a label for a table section, then we will return the settings attribute object
     *  Used to get attributeId and tagName to build the row note object to insert new table attribute rows
     *
     * @param string $inputLabel custom attribute label
     *
     * @param object $inputConfig The Apptivo app config
     *
     * @param ApptivoController $aApi The Apptivo controller obj
       *
     * @return ResultObject The value of the attribute
     */
    public static function getTableRowNoteAttributeObj(string $inputLabel, object $inputConfig, ApptivoController $aApi): ResultObject
    {
        $tableSectionResult = self::getTableSectionAttributeObjFromLabel($inputLabel, $inputConfig);
        if(!$tableSectionResult->isSuccessful) {
            return $tableSectionResult;
        }
        for($i = 0;$i < count($tableSectionResult->payload->attributes);$i++) {
            if($tableSectionResult->payload->attributes[$i]->isRowNote == true) {
                return ResultObject::success($tableSectionResult->payload->attributes[$i]);
            }
        }
        return ResultObject::fail('ERROR: getTableRowNoteAttributeObj: Unable to find rowNote attribute even though we located the table for inputLabel ('.$inputLabel.')');
    }
    
    /**
     * getTableSectionAttributeObjFromLabel
     * 
     *  Will return either false, or the string with the customAttribuiteId for a provided label and app config
     *
     * @param string $inputLabel custom attribute label
     *
     * @param object $inputConfig The Apptivo app config
       *
     * @return ResultObject The value of the attribute
     */
    public static function getTableSectionAttributeObjFromLabel(string $inputLabel, object $inputConfig): ResultObject
    {
        $webLayout = $inputConfig->webLayout;
        $sectionsNode = json_decode($webLayout);
        $sections = $sectionsNode->sections;
        $foundAttr = false;
        foreach($sections as $cSection) {
            $sectionName = $cSection->label;
            if(StringUtil::sComp($sectionName,$inputLabel)) {
                return ResultObject::success($cSection);
            }
        }
        return ResultObject::fail('getTableSectionAttributeIdFromLabel: Could not find our attribute to get a value from, check label ('.$inputLabel.')');
    }
}