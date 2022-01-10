<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use Exception;
use ToddMinerTech\ApptivoPhp\ApptivoController;
use ToddMinerTech\DataUtils\StringUtil;

/**
 * Class SystemUtils
 *
 * Manages functionas around authentication or other Apptivo system interactions.  Designed to be called statically from ApptivoController
 *
 * @package ToddMinerTech\ApptivoPhp
 */
class SystemUtils
{
    /**
     * getSession
     * 
     * Authenticates with the Apptivo api using configured user/pass/firm id and stores sessionKey on your Apptivo controller for usage elsewhere
     *
     * @param ApptivoController $aApi Your Apptivo controller object
     *
     * @return void stores your session data on the Apptivo 
     */
    public static function setSessionKey(ApptivoController $aApi): void
    {
        $apiUrl = 'https://api2.apptivo.com/app/'.
            'login'.
            '?a=login'.
            '&generateSessionkey=true'.
            '&getSessionToken=true';

        $postFormParams = [
            'emailId' => $aApi->sessionEmailId,
            'password' => $aApi->sessionPassword,
            'firmId' => $aApi->sessionFirmId
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
            if($decodedApiResponse && isset($decodedApiResponse->responseObject) && isset($decodedApiResponse->responseObject->authenticationKey)) {
                $aApi->sessionKey = $decodedApiResponse->responseObject->authenticationKey;
                return;
            }
        }
        throw new Exception('ApptivoPHP: SystemUtils: setSessionKey: Failed to retrieve a valid response from the Apptivo API. $aApi->sessionEmailId ('.$aApi->sessionEmailId.')');
    }
}