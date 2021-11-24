<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use Exception;
use Google\Cloud\Logging\LoggingClient;
use Illuminate\Support\Facades\Log;
use ToddMinerTech\ApptivoPhp\ApptivoController;
use ToddMinerTech\ApptivoPhp\ObjectDataUtils;
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
     * @return object Will return the table rows 
     */
    public static function getTableSectionRowsFromSectionLabel(string $tableSectionLabel, object $objectData, object $inputConfig): ?array
    {
        if(!$objectData || !isset($objectData->customAttributes)) {
            throw new Exception('ApptivoPhp: ObjectTableUtils: getTableSectionRowsFromSectionLabel: $objectData provided was not valid with a customAttributes attribute.  $objectData:  '.json_encode($objectData));
        }
        $tableSectionId = self::getTableSectionAttributeIdFromLabel($tableSectionLabel, $inputConfig);
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
     * @return string Will return the table section attribute id if found
     */
    public static function getTableSectionAttributeIdFromLabel(string $inputLabel, object $inputConfig): string
    {
        $webLayout = $inputConfig->webLayout;
        $sectionsNode = json_decode($webLayout);
        $sections = $sectionsNode->sections;
        $foundAttr = false;
        foreach($sections as $cSection) {
            $sectionName = $cSection->label;
            if(StringUtil::sComp($sectionName,$inputLabel)) {
                return $cSection->id;
            }
        }
        throw new Exception('ApptivoPhp: OBjectTableUtils: getTableSectionAttributeIdFromLabel: Could not find our attribute to get a value from, check label ('.$inputLabel.')',true);
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
     * @return object Will return the table rows 
     */
    public static function getTableSectionRowsFromSectionId(string $tableSectionId, object $objectData): ?array
    {
        if(!$objectData || !isset($objectData->customAttributes)) {
            throw new Exception('ApptivoPhp: ObjectDataUtils: getTableSectionRowsFromSectionId: $objectData provided was not valid with a customAttributes attribute.  $objectData:  '.json_encode($objectData));
        }
        foreach($objectData->customAttributes as $cAttr) {
            if($cAttr->customAttributeId == $tableSectionId) {
                return $cAttr->rows;
            }
        }
        return null;
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
    public static function getTableRowColIndexFromAttributeId(string $customAttributeId, object $tableRowObj): ?int
    {
        //IMPROVEMENT get this extracted into a class of data utilities for tables
        for($col = 0; $col < count($tableRowObj->columns); $col++) {
            if(StringUtil::sComp($tableRowObj->columns[$col]->customAttributeId,$customAttributeId)) {
                return $col;
            }
        }
        return null;
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
     * @return string The value of the attribute
     */
    public static function getTableRowAttrValueFromLabel(string $inputLabel, object $inputRowObj, object $inputConfig): ?string
    {
        $customAttributeIdToFind = ObjectDataUtils::getAttributeIdFromLabel($inputLabel,$inputConfig);
        if(!$inputRowObj && $inputRowObj->columns) {
            throw new Exception('ApptivoPhp: ObjectTableUtils: getTableRowAttrValueFromLabel: This table row had no columns availalbe.');
        }
        for($col=0;$col<count($inputRowObj->columns);$col++) {
            if($inputRowObj->columns[$col]->customAttributeId == $customAttributeIdToFind) {
                //logIt('returning customAttributeValue ('.$inputRowObj->columns[$col]->customAttributeValue.') from this json: '.json_encode($inputRowObj->columns[$col]));
                if(isset($inputRowObj->columns[$col]->customAttributeValue) && $inputRowObj->columns[$col]->customAttributeValue) {
                    return $inputRowObj->columns[$col]->customAttributeValue;
                }elseif(isset($inputRowObj->columns[$col]->attributeValues) && isset($inputRowObj->columns[$col]->attributeValues[0]) && isset($inputRowObj->columns[$col]->attributeValues[0]->attributeValue)) {
                    return $inputRowObj->columns[$col]->attributeValues[0]->attributeValue;
                }
            }
        }
        return '';
    }
}