<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use Exception;
use Google\Cloud\Logging\LoggingClient;
use Illuminate\Support\Facades\Log;
use ToddMinerTech\DataUtils\StringUtil;

/**
 * Class SearchUtils
 *
 * Class to perform any search functions along with processing search results from the Apptiv API.  Designed to be called statically from ApptivoController
 *
 * @package ToddMinerTech\apptivo-php-mt
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
         * @return array Returns an array of search results, should be empty if no results.  Throws an exception if a valid response is not received.
         */
        public static function getAllBySearchText(string $searchText, string $appNameOrId, ApptivoController $aApi, string $extraParams = ''): array
        {
            if(!$appNameOrId) {
                Throw new Exception('ApptivoPHP: ObjectCrud: read: No $appNameOrId value was provided.');
            }
            $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
            $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
                    $appParams->objectUrlName.
                    '?a=getAllBySearchText'.
                    '&searchText='.urlencode($searchText).
                    $extraParams.
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
                if($decodedApiResponse && $decodedApiResponse->data) {
                    return $decodedApiResponse->data;
                }
            }
            throw new Exception('ApptivoPHP: SearchUtils: getAllBySearchText: Failed to retrieve a valid response from the Apptivo API. $searchText ('.$searchText.')  $appNameOrId ('.$appNameOrId.')  $bodyContents ('.$bodyContents.')');
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
         * @return string Returns the matching ID or throws an exception
         */
        public static function getEmployeeIdFromName(string $employeeNameToFind, ApptivoController $aApi): string
        {
            $searchResults = $aApi->getAllBySearchText($employeeNameToFind, 'employees');

            foreach($searchResults as $cResult) {
                if(StringUtil::ssComp($employeeNameToFind,$cResult->fullName)) {
                    return (string)$cResult->employeeId;
                }
            }
            throw new Exception('ApptivoPHP: SearchUtils: getEmployeeIdFromName: unable to locate  a matching employee for $employeeNameToFind ('.$employeeNameToFind.')');
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
         * @return object the complete customer object
         */
        public static function getCustomerObjFromName(string $customerNameToFind, ApptivoController $aApi): object
        {
            //IMPROVEMENT - Extract some of this into utils
            $searchResults = $aApi->getAllBySearchText($customerNameToFind, 'customers');

            foreach($searchResults as $cResult) {
                if(StringUtil::ssComp($customerNameToFind,$cResult->customerName)) {
                    return $cResult;
                }
            }
            //IMPROVEMENT Get rid of exception so we can return nothing when nothing is found
            throw new Exception('ApptivoPHP: SearchUtils: getCustomerObjFromName: unable to locate  a matching employee for $customerNameToFind ('.$customerNameToFind.')');
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
         * @return string Returns the matching ID or throws an exception
         */
        public static function getCustomerIdFromName(string $customerNameToFind, ApptivoController $aApi): string
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
         * @return array list of every Apptivo object from that app
         */
	public static function getAllRecordsInApp(string $appNameOrId,  ApptivoController $aApi, int $maxRecords = 20000): array
        {
            $allSearchRecords = [];
            $i = 0;
            $numRecords = 5000;
            $loopComplete = false;
            Do {
                $startIndex = $i * $numRecords;
                $batchResult = self::dataManagementGetAll($appName, $startIndex, $numRecords);
                $batchData = $batchResult->data;
                if(is_array($batchData)) {
                    $allSearchRecords = array_merge($allSearchRecords,$batchData);
                    if($batchResult->countOfRecords < $numRecords) {
                        $loopComplete = true;
                    }
                }else{
                        $loopComplete = true;
                }
                $i++;
            }While ($loopComplete == false);

            return $allSearchRecords;
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
         * @return array list of the apptivo objects retrieved
         */
        private static function dataManagementGetAll(string $appNameOrId, ApptivoController $aApi, int $startIndex = 0, int $numRecords = 2000): array
        {
            $sessionData = $aApi->getSession();
            if(!$sessionData) {
                Throw new Exception('ApptivoPhp: SearchUtils: dataManagementGetAll: We had no sessionData, please first call setSessionCredentials before calling dataManagementGetAll');
            }
            
            if(!$appNameOrId) {
                Throw new Exception('ApptivoPHP: ObjectCrud: read: No $appNameOrId value was provided.');
            }
            $appParams = new \ToddMinerTech\ApptivoPhp\AppParams($appNameOrId);
            
            $apiUrl = 'https://api2.apptivo.com/app/dao/v6/'.
                'datamanagement'.
                '?a=getAll'.
                '&objectId='.$appParams->objectId.
                '&objectStatus=0'.
                '&startIndex='.$startIndex.
                '&numRecords='.$numRecords.
                $extraParams;

            $postFormParams = [
                'sessionKey' => $sessionData->responseObject->authenticationKey,
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
                    return $decodedApiResponse->data;
                }
            }
            throw new Exception('ApptivoPHP: SearchUtils: getAllBySearchText: Failed to retrieve a valid response from the Apptivo API. $searchText ('.$searchText.')  $appNameOrId ('.$appNameOrId.')  $bodyContents ('.$bodyContents.')');
        }
}