<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

/**
 * Class ObjectDataUtils
 *
 * Class to perform processing tasks related to object record such as retreiving or updating a specific field value.  Designed to be called statically from ApptivoController
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class ObjectDataUtils
{
    
    public static function getAttrValueFromLabel(string $inputLabel, string $inputObj, object $inputConfig): string
    {
        //For table attributes the inputLabel should be an array: ["Table Section Name","Attribute Name"]
        //If it's a multi select field with an array value we'll return the array untouched, or we'll create a comma delimited list from the result based on the input param
        $webLayout = $inputConfig->webLayout;
        $sectionsNode = json_decode($webLayout);
        $sections = $sectionsNode->sections;
        $foundAttr = false;

        foreach($sections as $cSection) {
            $sectionName = $cSection->label;
            $sectionAttributes = $cSection->attributes;

            //Proceed if we are checking all attributes, or if if its an array then we only proceed for a table that matches our label
            if( (!is_array($inputLabel)) || (is_array($inputLabel) && sComp($cSection->label,$inputLabel[0])) ) {
                foreach($sectionAttributes as $cAttr) {
                    if($cAttr->label) {
                        $labelName = $cAttr->label->modifiedLabel;
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
                        if($attributeType == 'Custom') {
                            //This is a potential attribute.  Now let's find the attribute with the matching label.  Both conditions for regular attribute and attribute in table
                            if( $cAttr->isEnabled && ( (!is_array($inputLabel) && sComp($labelName,$inputLabel)) || (is_array($inputLabel) && sComp($labelName,$inputLabel[1])) ) ) {
                                //We have matched the right attribute from settings.  Now match value if it's a dropdown or multi select.
                                $matchedAttr = $cAttr;
                                $foundAttr = true;
                                break;
                            }
                        }
                    }
                }	
            }
            if($foundAttr) {
                //Break the 2nd loop once we have attribute
                break;
            }
        }
        if($foundAttr) {
            //Now check if the attribute id exists in this object 
            foreach($inputObj->customAttributes as $cAttr) {
                if($cAttr->customAttributeId == $matchedAttr->attributeId) {
                    switch($attributeTag) {
                        case 'multiSelect':
                        case 'check':
                            return $cAttr->attributeValues;
                        break;
                        case 'currency':
                        case 'date':
                        case 'input':
                        case 'link':
                            return $cAttr->customAttributeValue;
                        break;
                        case 'number':
                        case 'reference':
                        case 'referenceField':
                        case 'select':
                        case 'textarea':
                            return $cAttr->customAttributeValue;
                        break;
                        default:
                            throw new Exception('This attribute was found but the attributeTag ('.$attributeTag.') is not yet supported for inputLabel ('.$inputLabel.')');
                    }
                }
            }
            //We didn't find the attribute, nothing here yet
            return '';
        }else{
            Log::debug('We could not locate this attribute inputLabel ('.$inputLabel.') within the settings');
            return '';
        }
    }
}
