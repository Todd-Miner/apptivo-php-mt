<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use ToddMinerTech\DataUtils\CountryStateUtil;
use ToddMinerTech\DataUtils\StringUtil;
use ToddMinerTech\DataUtils\ArrUtil;
use ToddMinerTech\MinerTechDataUtils\ResultObject;

/**
 * Class EmailUtil
 *
 * Class to help manage retrieval, processing, and delivery of emails using the Apptivo API
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class EmailUtil
{    
    /**
     * sendEmail
     * 
     * Send an email from the Apptivo API
     * 
     * @var object $emailData Apptivo email object data
     * 
     * return ResultObject returns the generated email object data
     */
    public static function sendEmail(object $emailData, ApptivoController $aApi): ResultObject
    {
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
    
    /**
     * sendEmailBasic
     * 
     * Wraps sendEmail to build the object for us when we don't care about associations and other details
     * 
     * @var string $fromAddress Send email from this address
     * 
     * @var string $toAddress Send email to this address
     * 
     * return ResultObject returns the generated email object data
     */
    public static function sendEmailBasic(string $fromEmail, string|array $toEmail, string $subject, string $body, ApptivoController $aApi): ResultObject
    {
        $emailData = new \stdClass;
        $emailData->fromAddress = [];
        $fromAddressObj = new \stdClass;
        $fromAddressObj->emailAddress = $fromEmail;
        $emailData->fromAddress[] = $fromAddressObj;
        $emailData->toAddress = [];
        if(is_array($toEmail)) {
            foreach($toEmail as $cEmail) {
                $toAddress = new \stdClass;
                $toAddress->emailAddress = $cEmail;
                $emailData->toAddress[] = $toAddress;
            }
        }else{
            $toAddress = new \stdClass;
            $toAddress->emailAddress = $toEmail;
            $emailData->toAddress[] = $toAddress;
        }
        $emailData->ccAddress = [];
        $emailData->bccAddress = [];
        $emailData->subject = $subject;
        $emailData->message = $body;
        $emailData->documents = [];
        $emailData->associations = [];
        $emailData->labels = [];
        $emailData->objectRefIds = null;
        $emailData->status = 'Send1';
        return self::sendEmail($emailData, $aApi);
    }     
}
