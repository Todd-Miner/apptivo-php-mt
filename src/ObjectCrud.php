<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use Exception;
use ToddMinerTech\ApptivoPhp\AppParams;
use ToddMinerTech\ApptivoPhp\ApptivoController;

/**
 * Class ObjectCrud
 *
 * Class to create, read, update, or delete apptivo objects
 *
 * @package ToddMinerTech\apptivo-php-mt
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
     * @return object Returns the newly created apptivo object
     */
    public static function create(string $appNameOrId, object $objectData, \ToddMinerTech\ApptivoPhp\ApptivoController $aApi, string $extraParams = ''): object
    {
        if(!$appNameOrId) {
            Throw new Exception('ApptivoPHP: ObjectCrud: create: No $appNameOrId value was provided.');
        }
        if(!$objectData) {
            Throw new Exception('ApptivoPHP: ObjectCrud: create: No $objectData value was provided.');
        }
        $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
        
        //For custom apps we need 1 extra param here.  It's returned back by objParams, just need to inject into extraParams.  If it's an extension of cases we need to use the case obj params, except the object id & extra app id param
        //IMPROVEMENT See if we can eliminate these completely within AppParams.
        $appParts = explode('-',$appNameOrId);
        if($app == 'customapp' || $objParams['objectUrlName'] == 'customapp') {
            $extraParams .= '&customAppObjectId='.$objParams['objectId'];	
        }
        $objectId = '&objectId='.$objParams['objectId'];
        $appIdStr = '&appId='.$objParams['objectId'];
        
        $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
            $appParams->objectUrlName.
            '?a=save'.
            $objIdStr.
            '&objectId='.$objectData->id.
            '&appId='.$objectData->id.
            $extraParams.
            '&apiKey='.$aApi->getApiKey().
            '&accessKey='.$aApi->getAccessKey().
            $aApi->getUserNameStr();
        
        $client = new \GuzzleHttp\Client();
        for($i = 1; $i <= $aApi->apiRetries+1; $i++) {
            sleep($aApi->apiSleepTime);
            $res = $client->request('POST', $apiUrl, [
                'form_params' => [
                    $appParams->objectDataName => json_encode($objectData)
                ]
            ]);
            $body = $res->getBody();
            $bodyContents = $body->getContents();
            $decodedApiResponse = json_decode($bodyContents);
            $returnObj = null;
            if($decodedApiResponse && isset($decodedApiResponse->id)) {
                $returnObj = $decodedApiResponse;
            } else if ($decodedApiResponse && isset($decodedApiResponse->data)) {
                $returnObj = $decodedApiResponse->data;
            } else if ($decodedApiResponse && isset($decodedApiResponse->responseObject)) {
                $returnObj = $decodedApiResponse->responseObject;
            } else if ($decodedApiResponse && isset($decodedApiResponse->customer)) {
                $returnObj = $decodedApiResponse->customer;
                //IMPROVEMENT - See if we can generate a mapped name for every day to handle dyanmically.  Not sure if any other apps do it this way.
            }
            if($returnObj) {
                break;
            }
        }
        if(!$returnObj) {
            throw new Exception('ApptivoPHP: ObjectCrud: create - failed to generate a $returnObj.  $bodyContents ('.$bodyContents.')');
        }
        return $returnObj;
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
     * @return object Returns the apptivo object
     */
    public static function read(string $appNameOrId, string $objectId, \ToddMinerTech\ApptivoPhp\ApptivoController $aApi): object
    {
        if(!$appNameOrId) {
            Throw new Exception('ApptivoPHP: ObjectCrud: read: No $appNameOrId value was provided.');
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
            if($decodedApiResponse && $decodedApiResponse->id) {
                $returnObj = $decodedApiResponse;
            } else if ($decodedApiResponse && $decodedApiResponse->data) {
                $returnObj = $decodedApiResponse->data;
            } else if ($decodedApiResponse && $decodedApiResponse->responseObject) {
                $returnObj = $decodedApiResponse->responseObject;
            }
            if($returnObj) {
                break;
            }
        }
        return $returnObj;
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
     * @return object Returns the updated apptivo object
     */
    public static function update(string $appNameOrId, array $attributeNames, array $attributeIds, object $objectData, bool $isCustomAttributeUpdate, \ToddMinerTech\ApptivoPhp\ApptivoController $aApi, string $extraParams = ''): object
    {
        if(!$appNameOrId) {
            Throw new Exception('ApptivoPHP: ObjectCrud: update: No $appNameOrId value was provided.');
        }
        $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
        
        if(!$attributeNames) {
            Throw new Exception('ApptivoPHP: ObjectCrud: update: No $attributeNames value was provided.');
        }
        //For contacts, maybe other apps too, attributeName should be singular
        if($appNameOrId == 'customers') {
            $aName = '';
        }else{
            $aName = 's';
        }
        $attributeNamesStr = '&attributeName'.$aName.'='.urlencode(json_encode($attributeNames));
        
        
        if(!$attributeIds) {
            Throw new Exception('ApptivoPHP: ObjectCrud: update: No $attributeIds value was provided.');
        }
        $attributeIdsStr = '&attributeIds='.urlencode(json_encode($attributeIds));
        
        $objIdStr = '';
        if($appNameOrId !== 'estimates') {
            $objIdStr = '&objectId='.$appParams->objectId;
        }
        
        $customAttrString = '&isCustomAttributesUpdate=';
        if($isCustomAttributeUpdate) {
            $customAttrString = '&isCustomAttributesUpdate=true';
        }    
        
        if(!$objectData) {
            Throw new Exception('ApptivoPHP: ObjectCrud: update: No $objectData value was provided.');
        }
        
        $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
            $appParams->objectUrlName.
            '?a=update'.
            $objIdStr.
            '&'.$appParams->objectIdName.'='.$objectData->id.
            $attributeNamesStr.
            $attributeIdsStr.
            $customAttrString.
            $extraParams.
            '&apiKey='.$aApi->getApiKey().
            '&accessKey='.$aApi->getAccessKey().
            $aApi->getUserNameStr();

        
        $client = new \GuzzleHttp\Client();
        for($i = 1; $i <= $aApi->apiRetries+1; $i++) {
            sleep($aApi->apiSleepTime);
            $res = $client->request('POST', $apiUrl, [
                'form_params' => [
                    $appParams->objectDataName => json_encode($objectData)
                ]
            ]);
            $body = $res->getBody();
            $bodyContents = $body->getContents();
            $decodedApiResponse = json_decode($bodyContents);
            $returnObj = null;
            if($decodedApiResponse && isset($decodedApiResponse->id)) {
                $returnObj = $decodedApiResponse;
            } else if ($decodedApiResponse && isset($decodedApiResponse->data)) {
                $returnObj = $decodedApiResponse->data;
            } else if ($decodedApiResponse && isset($decodedApiResponse->responseObject)) {
                $returnObj = $decodedApiResponse->responseObject;
            } else if ($decodedApiResponse && isset($decodedApiResponse->customer)) {
                $returnObj = $decodedApiResponse->customer;
                //IMPROVEMENT - See if we can generate a mapped name for every day to handle dyanmically.  Not sure if any other apps do it this way.
            }
            if($returnObj) {
                break;
            }
        }
        if(!$returnObj) {
            throw new Exception('ApptivoPHP: ObjectCrud: update - failed to generate a $returnObj.  $bodyContents ('.$bodyContents.')');
        }
        return $returnObj;
    }
}
