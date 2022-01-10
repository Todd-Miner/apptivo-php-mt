<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\ApptivoPhp\ApptivoController;
use ToddMinerTech\MinerTechDataUtils\ResultObject;
use ToddMinerTech\DataUtils\StringUtil;

/**
 * Class SearchUtils
 *
 * Class to perform any search functions along with processing search results from the Apptiv API.  Designed to be called statically from ApptivoController
 *
 * @package ToddMinerTech\ApptivoPhp
 */
class SearchUtils
{
    /* 
     * Generic Searches
     */

        /**
         * getAllFromSearchText
         * 
         * Provide a string to search for within an app and get an array of results.  Same as using the general keyword search in Apptivo UI.
         *
         * @param string $searchText The text to search with
         *
         * @param string $appNameOrId The apptivo name or id used to get app parameters
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @param string $extraParams Optional additional query string parameters.  This string must start with "&" like "&numRecords=50".  Must urlencode any values.
         *
         * @param bool $returnCountAndData Set to true if you want the return value to be an object with data & countOfRecords attributes.  Otherwise only data returned.
         *
         * @return ResultObject Returns an array of search results, should be empty if no results.  Throws an exception if a valid response is not received.
         */
        public static function getAllBySearchText(string $searchText, string $appNameOrId, ApptivoController $aApi, string $extraParams = '', bool $returnCountAndData = false): ResultObject
        {
            if(!$appNameOrId) {
                return ResultObject::fail('ApptivoPHP: ObjectCrud: read: No $appNameOrId value was provided.');
            }
            $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
            $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
                    $appParams->objectUrlName.
                    '?a=getAllBySearchText'.
                    '&searchText='.urlencode($searchText).
                    '&objectId='.$appParams->objectId.
                    $extraParams;

            $postFormParams = [
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
                if($decodedApiResponse && $decodedApiResponse->data) {
                    if($returnCountAndData) {
                        return ResultObject::success($decodedApiResponse);
                    }else{
                        return ResultObject::success($decodedApiResponse->data);
                    }
                }
            }
            return ResultObject::fail('ApptivoPHP: SearchUtils: getAllBySearchText: Failed to retrieve a valid response from the Apptivo API. $searchText ('.$searchText.')  $appNameOrId ('.$appNameOrId.')  $bodyContents ('.$bodyContents.')');
	}      
        
        /**
         * getAllBySearchTextPaged
         * 
         * Returns an array of all records (up to 10,000, limited by Apptivo API) that are returned by a getAllBySearchText request for any app
         * Although we have  10k limit, we can double it by reversing the sort order and grabbing the first 10k, then reversing and grabbing up to 10k until we hit the same ID already captured.
         *
         * @param string $searchText The text to search with
         *
         * @param string $appNameOrId The apptivo name or id used to get app parameters
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @param string $extraParams Optional additional query string parameters.  This string must start with "&" like "&numRecords=50".  Must urlencode any values.
         *
         * @param int $maxRecords Optional Number of records to retrieve before exiting the loop.
         *
         * @return ResultObject Returns an array of search results, should be empty if no results.  Throws an exception if a valid response is not received.
         */
        public static function getAllBySearchTextPaged(string $searchText, string $appNameOrId, ApptivoController $aApi, string $extraParams = '', int $maxRecords = 10000): ResultObject
        {
            $allSearchRecords = [];
            $i = 0;
            $numRecords = 250;
            //Get the first batch to pull countOfRecords.  Could optimizie to skip query, just leaving 1 extra query since it's usually not a big deal
            $batchResultObj = self::getAllBySearchText($searchText, $appNameOrId, $aApi, '&startIndex=0&numRecords='.$numRecords.$extraParams, true);
            if(!$batchResultObj->isSuccessful) {
                return $batchResultObj;
            }
            $batchResult = $batchResultObj->payload;
            if($batchResult && $batchResult->countOfRecords > 5000) {
                $reverseRun = true;
            }else{
                $reverseRun = false;
            }
            $loopComplete = false;
            Do {
                $startIndex = $i * $numRecords;
                $batchResultObj = self::getAllBySearchText($searchText, $appNameOrId, $aApi, '&startIndex='.$startIndex.'&numRecords='.$numRecords.$extraParams, true);
                if(!$batchResultObj->isSuccessful) {
                    return $batchResultObj;
                }
                $batchResult = $batchResultObj->payload;
                $batchData = $batchResult->data;
                //Loop opportunities, then loop the attrList in order and add columns even when blank.
                if(is_array($batchData)) {
                    $allSearchRecords = array_merge($allSearchRecords,$batchData);
                    if($startIndex + $numRecords + 1 > $batchResult->countOfRecords || $startIndex + $numRecords + 1 > 4999 || $startIndex + $numRecords + 1 > $maxRecords) {
                        $loopComplete = true;
                    }
                }else{
                    $loopComplete = true;
                }
                $i++;
            }While ($loopComplete == false);
            if($reverseRun) {
                $reverseExtraParams = str_replace('=asc','=desc',$extraParams);
                $i = 0;
                $loopComplete = false;
                Do {
                    $startIndex = $i * $numRecords;
                    $batchResultObj = self::getAllBySearchText($searchText, $appNameOrId, $aApi, '&startIndex='.$startIndex.'&numRecords='.$numRecords.$reverseExtraParams, true);
                    if(!$batchResultObj->isSuccessful) {
                        return $batchResultObj;
                    }
                    $batchResult = $batchResultObj->payload;
                    $batchData = $batchResult->data;
                    //Loop opportunities, then loop the attrList in order and add columns even when blank.
                    if(is_array($batchData)) {
                        //For now it's just merging together.  Later on this should be updated to gather the reverse batch, then reverse the sort so we can keep a consistent sort in the final data batch
                        //One extra condition to detect if the id is already in the array.  Must loop everything since we dont have a singular set of IDs.  Not efficient but best quick solution.
                        //Check last value first, and if that is a dupe then we check them all and selectively add, otherwise add em all
                        $filteredBatchData = $batchData;
                        for($asN = count($allSearchRecords)-1;$asN < 51;$asN--) {
                            for($bdN=0;$bdN<count($batchData);$bdN++) {
                                if($allSearchRecords[$asN]->id == $batchData[count($batchData)]->id) {
                                    unset($filteredBatchData[$bdN]);
                                    $loopComplete = true;
                                }
                            }
                        }
                        $allSearchRecords = array_merge($allSearchRecords,$filteredBatchData);
                        if($startIndex + $numRecords + 1 > $batchResult->countOfRecords || $startIndex + $numRecords + 1 > 4999 || $startIndex + $numRecords + 1 > $maxRecords) {
                            $loopComplete = true;
                        }
                    }else{
                        $loopComplete = true;
                    }
                    $i++;
                }While ($loopComplete == false);
            }
            return ResultObject::success($allSearchRecords);
        }
        
        /**
         * getAllByCustomView
         * 
         * Get an array of results from a custom view VIEWCODE
         *
         * @param string $viewCode Tee all uppercase VIEWCODE for this custom view
         *
         * @param string $appNameOrId The apptivo name or id used to get app parameters
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @param string $extraParams Optional additional query string parameters.  This string must start with "&" like "&numRecords=50".  Must urlencode any values.
         *
         * @param bool $returnCountAndData Set to true if you want the return value to be an object with data & countOfRecords attributes.  Otherwise only data returned.
         *
         * @return ResultObject Returns an array of search results, should be empty if no results.  Throws an exception if a valid response is not received.
         */
	public static function getAllByCustomView(string $viewCode, string $appNameOrId, ApptivoController $aApi, string $extraParams = '', bool $returnCountAndData = false): ResultObject
        {
            if(!$appNameOrId) {
                return ResultObject::fail('ApptivoPHP: SearchUtils: getAllByCustomView: No $appNameOrId value was provided.');
            }
            $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
            $apiUrl = 'https://api2.apptivo.com/app/dao/v5/'.
                    'appsettings'.
                    '?a=getAllByCustomView'.
                    '&viewCode='.urlencode($viewCode).
                    '&objectId='.$appParams->objectId.
                    $extraParams;

            $postFormParams = [
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
                if($decodedApiResponse && $decodedApiResponse->data) {
                    if($returnCountAndData) {
                        return ResultObject::success($decodedApiResponse);
                    }else{
                        return ResultObject::success($decodedApiResponse->data);
                    }
                }
            }
            return ResultObject::fail('ApptivoPHP: SearchUtils: getAllByCustomView: Failed to retrieve a valid response from the Apptivo API. $viewCode ('.$viewCode.')  $appNameOrId ('.$appNameOrId.')  $bodyContents ('.$bodyContents.')');
	}
        
        /**
         * getAllByCustomViewPaged
         * 
         * Returns an array of all records (up to 10,000, limited by Apptivo API) that are returned by a getAllBySearchText request for any app
         * Although we have  10k limit, we can double it by reversing the sort order and grabbing the first 10k, then reversing and grabbing up to 10k until we hit the same ID already captured.
         *
         * @param string $viewCode Tee all uppercase VIEWCODE for this custom view
         *
         * @param string $appNameOrId The apptivo name or id used to get app parameters
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @param string $extraParams Optional additional query string parameters.  This string must start with "&" like "&numRecords=50".  Must urlencode any values.
         *
         * @param int $maxRecords Optional Number of records to retrieve before exiting the loop.
         *
         * @return ResultObject Returns an array of search results, should be empty if no results.  Throws an exception if a valid response is not received.
         */
	public static function getAllByCustomViewPaged(string $viewCode, string $appNameOrId, ApptivoController $aApi, string $extraParams = '', int $maxRecords = 10000): ResultObject
        {
            $allSearchRecords = [];
            $i = 0;
            $numRecords = 250;
            //Get the first batch to pull countOfRecords.  Could optimizie to skip query, just leaving 1 extra query since it's usually not a big deal
            $batchResultObj = self::getAllByCustomView($viewCode, $appNameOrId, $aApi, '&startIndex=0&numRecords='.$numRecords.$extraParams, true);
            if(!$batchResultObj->isSuccessful) {
                return $batchResultObj;
            }
            $batchResult = $batchResultObj->payload;
            if($batchResult && $batchResult->countOfRecords > 5000) {
                $reverseRun = true;
            }else{
                $reverseRun = false;
            }
            $loopComplete = false;
            Do {
                $startIndex = $i * $numRecords;
                if($startIndex > 0) {
                    $batchResultObj = self::getAllByCustomView($viewCode, $appNameOrId, $aApi, '&startIndex='.$startIndex.'&numRecords='.$numRecords.$extraParams, true);
                    if(!$batchResultObj->isSuccessful) {
                        return $batchResultObj;
                    }
                    $batchResult = $batchResultObj->payload;
                }
                $batchData = $batchResult->data;
                //Loop opportunities, then loop the attrList in order and add columns even when blank.
                if(is_array($batchData)) {
                    $allSearchRecords = array_merge($allSearchRecords,$batchData);
                    if($startIndex + $numRecords + 1 > $batchResult->countOfRecords || $startIndex + $numRecords + 1 > 4999 || $startIndex + $numRecords + 1 > $maxRecords) {
                        $loopComplete = true;
                    }
                }else{
                    $loopComplete = true;
                }
                $i++;
            }While ($loopComplete == false);
            if($reverseRun) {
                $reverseExtraParams = str_replace('=asc','=desc',$extraParams);
                $i = 0;
                $loopComplete = false;
                Do {
                    $startIndex = $i * $numRecords;
                    $batchResultObj = self::getAllByCustomView($viewCode, $appNameOrId, $aApi, '&startIndex='.$startIndex.'&numRecords='.$numRecords.$reverseExtraParams, true);
                    if(!$batchResultObj->isSuccessful) {
                        return $batchResultObj;
                    }
                    $batchResult = $batchResultObj->payload;
                    $batchData = $batchResult->data;
                    //Loop opportunities, then loop the attrList in order and add columns even when blank.
                    if(is_array($batchData)) {
                        //For now it's just merging together.  Later on this should be updated to gather the reverse batch, then reverse the sort so we can keep a consistent sort in the final data batch
                        //One extra condition to detect if the id is already in the array.  Must loop everything since we dont have a singular set of IDs.  Not efficient but best quick solution.
                        //Check last value first, and if that is a dupe then we check them all and selectively add, otherwise add em all
                        $filteredBatchData = $batchData;
                        for($asN = count($allSearchRecords)-1;$asN < 51;$asN--) {
                            for($bdN=0;$bdN<count($batchData);$bdN++) {
                                if($allSearchRecords[$asN]->id == $batchData[count($batchData)]->id) {
                                    unset($filteredBatchData[$bdN]);
                                    $loopComplete = true;
                                }
                            }
                        }
                        $allSearchRecords = array_merge($allSearchRecords,$filteredBatchData);
                        if($startIndex + $numRecords + 1 > $batchResult->countOfRecords || $startIndex + $numRecords + 1 > 4999 || $startIndex + $numRecords + 1 > $maxRecords) {
                            $loopComplete = true;
                        }
                    }else{
                        $loopComplete = true;
                    }
                    $i++;
                }While ($loopComplete == false);
            }
            return ResultObject::success($allSearchRecords);
	}
        
    /* 
     * Object specific search wrappers
     */
    
    
        /**
         * getEmployeeIdFromName
         * 
         * Provide a name and locate the matching reference id from the employees app
         *
         * @param string $employeeNameToFind The first & last name (space in between) Ex. "Todd Miner"
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @return ResultObject Returns the matching ID or throws an exception
         */
        public static function getEmployeeIdFromName(string $employeeNameToFind, ApptivoController $aApi): ResultObject
        {
            $searchResultsResult = $aApi->getAllBySearchText($employeeNameToFind, 'employees');
            if(!$searchResultsResult-isSuccessful) {
                return ResultObject::fail($searchResultsResult->payload);
            }
            $searchResults = $searchResultsResult->payload;
            foreach($searchResults as $cResult) {
                if(StringUtil::ssComp($employeeNameToFind,$cResult->fullName)) {
                    return ResultObject::success((string)$cResult->employeeId);
                }
            }
            return ResultObject::fail('ApptivoPHP: SearchUtils: getEmployeeIdFromName: unable to locate  a matching employee for $employeeNameToFind ('.$employeeNameToFind.')');
        }
    
        /**
         * getCustomerObjFromName
         * 
         * Provide a name and locate the matching reference id from the customers app
         *
         * @param string $customerNameToFind The customer name (space in between) Ex. "Todd Miner"
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @return ResultObject the complete customer object
         */
        public static function getCustomerObjFromName(string $customerNameToFind, ApptivoController $aApi): ResultObject
        {
            //IMPROVEMENT - Extract some of this into utils
            $searchResultsResult = $aApi->getAllBySearchText($customerNameToFind, 'customers');
            if(!$searchResultsResult->isSuccessful) {
                return ResultObject::fail($searchResultsResult->payload);
            }
            $searchResults = $searchResultsResult->payload;
            foreach($searchResults as $cResult) {
                if(StringUtil::ssComp($customerNameToFind,$cResult->customerName)) {
                    return ResultObject::success($cResult);
                }
            }
            //IMPROVEMENT Get rid of exception so we can return nothing when nothing is found
            return ResultObject::fail('ApptivoPHP: SearchUtils: getCustomerObjFromName: unable to locate  a matching employee for $customerNameToFind ('.$customerNameToFind.')');
        }
    
        /**
         * getCustomerIdFromName
         * 
         * Wraps getCustomerObjFromName
         *
         * @param string $customerNameToFind The customer name (space in between) Ex. "Todd Miner"
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @return ResultObject Returns the matching ID or throws an exception
         */
        public static function getCustomerIdFromName(string $customerNameToFind, ApptivoController $aApi): ResultObject
        {
            return self::getCustomerObjFromName($customerNameToFind, $aApi)->customerId;
        }
        
    
        /**
         * getAllRecordsInApp
         * 
         * Wraps dataManagementGetAll to retrieve all records in an application with no filters*. 
         * *Need to verify whether deleted records and inactive visibility statuses are included
         *
         * @param string $appNameOrId The Apptivo name or app id
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @param int $maxRecords If you want to cap the total records retrieved
         *
         * @return ResultObject list of every Apptivo object from that app
         */
	public static function getAllRecordsInApp(string $appNameOrId,  ApptivoController $aApi, int $maxRecords = 20000): ResultObject
        {
            $allSearchRecords = [];
            $i = 0;
            $numRecords = 5000;
            $loopComplete = false;
            Do {
                $startIndex = $i * $numRecords;
                $batchDataResult = self::dataManagementGetAll($appNameOrId, $aApi, $startIndex, $numRecords);
                if(!$batchDataResult->isSuccessful) {
                    return ResultObject::fail($batchDataResult->payload);
                }
                $batchData = $batchDataResult->payload->data;
                if(is_array($batchData)) {
                    $allSearchRecords = array_merge($allSearchRecords,$batchData);
                    if($batchDataResult->payload->countOfRecords < $numRecords) {
                        $loopComplete = true;
                    }
                }else{
                        $loopComplete = true;
                }
                $i++;
            }While ($loopComplete == false);

            return ResultObject::success($allSearchRecords);
        }
    
        /**
         * dataManagementGetAll
         * 
         * Uses special data management endpoint designed to retrieve data in bulk.
         * Use this when trying to load data for mass processing rather than search for specific records.
         *
         * @param string $appNameOrId The Apptivo name or app id
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @param int $startIndex Starting index for results, 0 index
         *
         * @param int $numRecords Number of results to retrieve
         *
         * @return ResultObject Object with data and countOfRecords attributes
         */
        private static function dataManagementGetAll(string $appNameOrId, ApptivoController $aApi, int $startIndex = 0, int $numRecords = 2000): ResultObject
        {
            if(!$aApi->sessionKey) {
                return ResultObject::fail('ApptivoPhp: SearchUtils: dataManagementGetAll: We had no sessionData, please first call setSessionCredentials before calling dataManagementGetAll');
            }
            
            if(!$appNameOrId) {
                return ResultObject::fail('ApptivoPHP: ObjectCrud: read: No $appNameOrId value was provided.');
            }
            $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
            
            $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
                'datamanagement'.
                '?a=getAll'.
                '&objectId='.$appParams->objectId.
                '&objectStatus=0'.
                '&startIndex='.$startIndex.
                '&numRecords='.$numRecords;

            $postFormParams = [
                'sessionKey' => $aApi->sessionKey,
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
                if($decodedApiResponse && $decodedApiResponse->data) {
                    return ResultObject::success($decodedApiResponse);
                }
            }
            return ResultObject::fail('ApptivoPHP: SearchUtils: dataManagementGetAll: Failed to retrieve a valid response from the Apptivo API. $appNameOrId ('.$appNameOrId.')  $bodyContents ('.$bodyContents.')');
        }
        
        /**
         * getObjectFromKeywordSearchAndCriteria
         * 
         * Uses special data management endpoint designed to retrieve data in bulk.
         * Use this when trying to load data for mass processing rather than search for specific records.
         *
         * @param string $appNameOrId The Apptivo name or app id
         *
         * @param array $fieldToMatch The field name we use to match this record.  Must be an array as per standard conventions to work with attributes.
         *
         * @param string $valueToMatch The regex value we will locate within fieldToMatch 
         *
         * @param ApptivoController $aApi Your Apptivo controller object
         *
         * @return ResultObject The first object we match from the search results
         */
        public static function getObjectFromKeywordSearchAndCriteria(array $fieldToMatch, string $valueToMatch, string $appNameOrId, ApptivoController $aApi): ResultObject
        {
            if(!$appNameOrId) {
                return ResultObject::fail('ApptivoPhp: SearchUtils: getObjectFromSearchCriteria: Missing $appNameOrId');
            }
            if(!$fieldToMatch) {
                return ResultObject::fail('ApptivoPhp: SearchUtils: getObjectFromSearchCriteria: Missing $fieldToMatch');
            }
            $resultObject = $aApi->getAllBySearchText($valueToMatch, $appNameOrId);
            if(!$resultObject->isSuccessful) {
                return ResultObject::fail($resultObject->payload);
            }
            foreach($resultObject->payload as $cResult) {
                $currentFieldValueResult = $aApi->getAttrDetailsFromLabel($fieldToMatch, $cResult, $appNameOrId);
                if(!$currentFieldValueResult->isSuccessful) {
                    return ResultObject::fail($currentFieldValueResult->payload);
                }
                $matches = null;
                preg_match($valueToMatch, $currentFieldValueResult->payload->attrValue, $matches);
                if($matches) {
                    return ResultObject::success($cResult);
                }
            }
            return ResultObject::fail('ApptivoPhp: SearchUtils: getObjectFromKeywordSearchAndCriteria: Unable to locate a match for this search. $fieldToMatch:  '.json_encode($fieldToMatch).'   $valueToMatch ('.$valueToMatch.')   $appNameOrId ('.$appNameOrId.')');
        }
}