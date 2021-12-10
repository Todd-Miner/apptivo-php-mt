<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\DataUtils\CountryStateUtil;
use ToddMinerTech\DataUtils\StringUtil;
use ToddMinerTech\DataUtils\ArrUtil;
use ToddMinerTech\ApptivoPhp\ResultObject;

/**
 * Class EmailUtil
 *
 * Class to help manage retrieval, processing, and delivery of emails using the Apptivo API
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class EmailUtil
{
    /**  @var ApptivoController $aApi The Miner Tech Apptivo package to interact with the Apptivo API */
    private $aApi; 
    
    function __construct(ApptivoController $aApi)
    {
        $this->aApi = $aApi;
    }
    
    public function sendEmail(object $emailData): ResultObject
    {
        //dev temp - original query params
        //'&objectId='.$objectId.'&objectRefId='.$objectRefId.'&isFromApp='.$isFromApp.'&closeObject='.$closeObject.'

        $apiUrl = 'https://api2.apptivo.com/app/dao/emails'.
                '?a=send&'.
                $aApi->getUserNameStr();

        $postFormParams = [
            'emailData' => json_encode($emailData),
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
            }
        }
        //If we exhausted our retries we fail out here
        return ResultObject::fail('ApptivoPHP: ObjectCrud: create - failed to generate a $returnObj.  $bodyContents ('.$bodyContents.')');
    }
    function sendEmailBasic($fromEmail,$toEmail,$subject,$body,$assObj,$isFromApp = 'App',$closeObject = 'false',$status = 'Send1') {
        
    }
            
}
