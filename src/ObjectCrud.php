<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\ApptivoPhp\AppParams;
use ToddMinerTech\ApptivoPhp\ApptivoController;
use ToddMinerTech\ApptivoPhp\Exceptions\RuntimeGetConfigException;
use ToddMinerTech\MinerTechDataUtils\ResultObject;

/**
 * Class ObjectCrud
 *
 * Class to create, read, update, or delete apptivo objects
 *
 * @package ToddMinerTech\ApptivoPhp
 */
class ObjectCrud
{
    /**
     * create
     * 
     * Update an existing Apptivo object, wraps the save method
     *
     * @param string $appNameOrId The apptivo app name or internal app id for this record 
     *
     * @param object $objectData The complete object data with updates
     *
     * @param ApptivoController $aApi Your Apptivo controller object
     *
     * @param string $extraParams Extra query string parameters to apply
     *
     * @return ResultObject
     */
    public static function create(string $appNameOrId, object $objectData, \ToddMinerTech\ApptivoPhp\ApptivoController $aApi, string $extraParams = ''): ResultObject
    {
        if(!$appNameOrId) {
            return ResultObject::fail('ApptivoPHP: ObjectCrud: create: No $appNameOrId value was provided.');
        }
        if(!$objectData) {
            return ResultObject::fail('ApptivoPHP: ObjectCrud: create: No $objectData value was provided.');
        }
        $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
        
        //For custom apps we need 1 extra param here.  It's returned back by objParams, just need to inject into extraParams.  If it's an extension of cases we need to use the case obj params, except the object id & extra app id param
        //IMPROVEMENT See if we can eliminate these completely within AppParams.
        $appParts = explode('-',$appNameOrId);
        if($appParts[0] == 'customapp' || $appParams->objectUrlName == 'customapp') {
            $extraParams .= '&customAppObjectId='.$appParams->objectId;	
        }
        
        $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
            $appParams->objectUrlName.
            '?a=save'.
            '&objectId='.$appParams->objectId.
            '&appId='.$appParams->objectId.
            $extraParams.
            $aApi->getUserNameStr();        
        
        $postFormParams = [
            $appParams->objectDataName => json_encode($objectData),
            'apiKey' => $aApi->getApiKey(),
            'accessKey' => $aApi->getAccessKey()
        ];

        $client = new \GuzzleHttp\Client();
        for($i = 1; $i <= $aApi->apiRetries+1; $i++) {
            sleep($aApi->apiSleepTime);
            $res = $client->request('POST', $apiUrl, [
                'form_params' => $postFormParams
            ]);
            $body = $res->getBody();
            $bodyContents = $body->getContents();
            $decodedApiResponse = json_decode($bodyContents);
            $returnObj = null;
            if($decodedApiResponse && isset($decodedApiResponse->id)) {
                $returnObj = $decodedApiResponse;
                return ResultObject::success($decodedApiResponse);
            } else if ($decodedApiResponse && isset($decodedApiResponse->data)) {
                return ResultObject::success($decodedApiResponse->data);
            } else if ($decodedApiResponse && isset($decodedApiResponse->responseObject)) {
                return ResultObject::success($decodedApiResponse->responseObject);
            } else if ($decodedApiResponse && isset($decodedApiResponse->customer)) {
                return ResultObject::success($decodedApiResponse->customer);
                //IMPROVEMENT - See if we can generate a mapped name for every day to handle dyanmically.  Not sure if any other apps do it this way.
            } else if ($decodedApiResponse && isset($decodedApiResponse->csCase)) {
                return ResultObject::success($decodedApiResponse->csCase);
            }
        }
        //If we exhausted our retries we fail out here
        return ResultObject::fail('ApptivoPHP: ObjectCrud: create - failed to generate a $returnObj.  $bodyContents ('.$bodyContents.')');
    }
    
    /**
     * read
     * 
     * Get an object by it's internal Apptivo ID.  Wraps the getById method in Apptivo.
     *
     * @param string $appNameOrId The apptivo app name or internal app id for this record 
     *
     * @param string $objectId The apptivo object id you want to retrieve - find in the URL or the id attribute of any record
     *
     * @return ResultObject
     */
    public static function read(string $appNameOrId, string $objectId, \ToddMinerTech\ApptivoPhp\ApptivoController $aApi): ResultObject
    {
        if(!$appNameOrId) {
            return ResultObject::fail('ApptivoPHP: ObjectCrud: read: No $appNameOrId value was provided.');
        }
        $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
        $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
                $appParams->objectUrlName.
                '?a=getById&'.
                $appParams->objectIdName.'='.$objectId.
                '&apiKey='.$aApi->getApiKey().
                '&accessKey='.$aApi->getAccessKey();
        
        $client = new \GuzzleHttp\Client();
        for($i = 1; $i <= $aApi->apiRetries+1; $i++) {
            sleep($aApi->apiSleepTime);
            $res = $client->request('GET', $apiUrl);
            $body = $res->getBody();
            $bodyContents = $body->getContents();
            $decodedApiResponse = json_decode($bodyContents);
            $returnObj = null;
            if($decodedApiResponse && isset($decodedApiResponse->id)) {
                return ResultObject::success($decodedApiResponse);
            } else if ($decodedApiResponse && isset($decodedApiResponse->data)) {
                return ResultObject::success($decodedApiResponse->data);
            } else if ($decodedApiResponse && isset($decodedApiResponse->responseObject)) {
                return ResultObject::success($decodedApiResponse->responseObject);
            }
        }
        return ResultObject::fail('ApptivoPhP: ObjectCrud: Read: Could not retrieve an Apptivo object for this request. $appNameOrId ('.$appNameOrId.')  $objectId ('.$objectId.')     $bodyContents:  '.$bodyContents);
    }
    
    /**
     * update
     * 
     * Update an existing Apptivo object, wraps the update method
     *
     * @param string $appNameOrId The apptivo app name or internal app id for this record 
     *
     * @param array $attributeNames The list of attribute name(s) that are being updated
     *
     * @param array $attributeIds The list of attribute id(s) that are being updated
     *
     * @param object $objectData The complete object data with updates
     *
     * @param string $extraParams Extra query string parameters to apply
     *
     * @return ResultObject
     */
    public static function update(string $appNameOrId, array $attributeNames, array $attributeIds, object $objectData, bool $isCustomAttributeUpdate, bool $isAddressUpdate, \ToddMinerTech\ApptivoPhp\ApptivoController $aApi, string $extraParams = ''): ResultObject
    {
        if(!$appNameOrId) {
            return ResultObject::fail('ApptivoPHP: ObjectCrud: update: No $appNameOrId value was provided.');
        }
        $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);

        $objIdStr = '';
        if($appNameOrId !== 'estimates' && strpos($appNameOrId, 'cases-') === false) {
            $objIdStr = '&objectId='.$appParams->objectId;
        }
        
        $appIdStr = '';
        if(strpos($appNameOrId, 'cases-') !== false) {
            $appIdStr = '&appId='.$appParams->objectId;
        }
        
        $customAttrString = '&isCustomAttributesUpdate=';
        if($isCustomAttributeUpdate) {
            $customAttrString = '&isCustomAttributesUpdate=true';
        }   
        
        $addressAttrString = '';
        if($isAddressUpdate) {
            $addressAttrString = '&isAddressUpdate=true';
        }    
        
        if(!$objectData) {
            return ResultObject::fail('ApptivoPHP: ObjectCrud: update: No $objectData value was provided.');
        }
        
        $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
            $appParams->objectUrlName.
            '?a=update'.
            $objIdStr.
            $appIdStr.
            '&'.$appParams->objectIdName.'='.$objectData->id.
            $customAttrString.
            $addressAttrString.
            $extraParams.
            $aApi->getUserNameStr();
        
        //For contacts, maybe other apps too, attributeName should be singular
        if(in_array($appNameOrId,['customers', 'items', 'cases']) || strpos($appNameOrId, 'cases-') !== false) {
            $attributeNameStr = 'attributeName';
        }else{
            $attributeNameStr = 'attributeNames';
        }
        $postFormParams = [
            $appParams->objectDataName => json_encode($objectData),
            $attributeNameStr => json_encode($attributeNames),
            'attributeIds' => json_encode($attributeIds),
            'apiKey' => $aApi->getApiKey(),
            'accessKey' => $aApi->getAccessKey()
        ];

        $client = new \GuzzleHttp\Client();
        for($i = 1; $i <= $aApi->apiRetries+1; $i++) {
            sleep($aApi->apiSleepTime);
            $res = $client->request('POST', $apiUrl, [
                'form_params' => $postFormParams
            ]);
            $body = $res->getBody();
            $bodyContents = $body->getContents();
            $decodedApiResponse = json_decode($bodyContents);
            $returnObj = null;
            if($decodedApiResponse && isset($decodedApiResponse->id)) {
                return ResultObject::success($decodedApiResponse->id);
            } else if ($decodedApiResponse && isset($decodedApiResponse->data)) {
                return ResultObject::success($decodedApiResponse->data);
            } else if ($decodedApiResponse && isset($decodedApiResponse->responseObject)) {
                return ResultObject::success($decodedApiResponse->responseObject);
            } else if ($decodedApiResponse && isset($decodedApiResponse->customer)) {
                return ResultObject::success($decodedApiResponse->customer);
                //IMPROVEMENT - See if we can generate a mapped name for every object within AppParams to handle dyanmically.
                //Not sure if any other apps do it this way.  Might also be different for create vs update etc
            } else if ($decodedApiResponse && isset($decodedApiResponse->csCase)) {
                return ResultObject::success($decodedApiResponse->csCase);
            }
        }
        return ResultObject::fail('ApptivoPHP: ObjectCrud: update - failed to generate a $returnObj.  $bodyContents ('.$bodyContents.')   json_encode($objectData):    '.json_encode($objectData));
    }
}
