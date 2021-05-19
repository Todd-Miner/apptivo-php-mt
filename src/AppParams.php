<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

use Exception;

/**
 * Class AppParameters
 *
 * Class to map an app name/id to static values that are required for each app.  No raw source for these right now, manually tested and documented.
 *
 * @package ToddMinerTech\apptivo-php-mt
 */
class AppParams
{
    /**  @var string $objectSingularName Singular name sometimes used in API requests */
    public $objectSingularName;
    /**  @var string $objectUrlName text used within the api endpoint url */
    public $objectUrlName;
    /**  @var string $objectDataName In the request body we pass something like projectData:{json object} - this is the "projectData" value */
    public $objectDataName;
    /**  @var string $objectIdName In request parameters we pass something like caseId=88929 - this is the "caseId" value */
    public $objectIdName;
    /**  @var string $objectId The internal Apptivo app ID number.  Static numbers for standard apps, unique numbers for custom apps. */
    public int $objectId;
    /**  @var string $appName The internal text representation of the app name */
    public string $appName = '';
    
    function __construct(string $appNameOrId) {
        //Check if we have a hyphen being passed in.  This indicates it's either a custom app, or an extension of cases.
        $appNameOrIdParts = explode('-',$appNameOrId);
        $appId = '';
        if(count($appNameOrIdParts) > 1) {
            $appId = intVal($appNameOrIdParts[1]);
            $this->appName = $appNameOrIdParts[0];
        }
        //Set the standard parameters to be used in the URL below
        switch(strtolower($appNameOrId)) {
            case 59:
            case 'cases':
                $this->objectSingularName = 'case';
                $this->objectUrlName = 'cases';
                $this->objectDataName = 'caseData';
                $this->objectIdName = 'caseId';
                if($appId) {
                    $this->objectId = $appId;
                }else{
                    $this->objectId = 59;
                }
                break;
            case 2:
            case 'contacts':
                $this->objectSingularName = 'contact';
                $this->objectUrlName = 'contacts';
                $this->objectDataName = 'contactData';
                $this->objectIdName = 'contactId';
                $this->objectId = 2;
                break;
            case 3:
            case 'customers':
                $this->objectSingularName = 'customer';
                $this->objectUrlName = 'customers';
                $this->objectDataName = 'customerData';
                $this->objectIdName = 'customerId';
                $this->objectId = 3;
                break;
            case 'customapp':
                $this->objectSingularName = 'customapp';
                $this->objectUrlName = 'customapp';
                $this->objectDataName = 'customAppData';
                $this->objectIdName = 'customAppId';
                $this->objectId = $appNameOrIdParts[1];
                break;
            case 8:
            case 'employees':
                $this->objectSingularName = 'employee';
                $this->objectUrlName = 'employees';
                $this->objectDataName = 'employeeData';
                $this->objectIdName = 'employeeId';
                $this->objectId = 8;
                break;
            case 155:
            case 'estimates':
                $this->objectSingularName = 'estimate';
                $this->objectUrlName = 'estimates';
                $this->objectDataName = 'estimateData';
                $this->objectIdName = 'estimateId';
                $this->objectId = 155;
                break;
            case 33:
            case 'invoices':
                $this->objectSingularName = 'invoice';
                $this->objectUrlName = 'invoice';
                $this->objectDataName = 'invoiceData';
                $this->objectIdName = 'invoiceId';
                $this->objectId = 33;
                break;
            case 13:
            case 'items':
                $this->objectSingularName = 'item';
                $this->objectUrlName = 'items';
                $this->objectDataName = 'itemData';
                $this->objectIdName = 'itemId';
                $this->objectId = 13;
                break;
            case 4:
            case 'leads':
                $this->objectSingularName = 'lead';
                $this->objectUrlName = 'leads';
                $this->objectDataName = 'leadData';
                $this->objectIdName = 'leadId';
                $this->objectId = 4;
                break;
            case 11:
            case 'opportunities':
                $this->objectSingularName = 'opportunity';
                $this->objectUrlName = 'opportunities';
                $this->objectDataName = 'opportunityData';
                $this->objectIdName = 'opportunityId';
                $this->objectId = 11;
                break;
            case 12:
            case 'orders':
                $this->objectSingularName = 'order';
                $this->objectUrlName = 'orders';
                $this->objectDataName = 'orderData';
                $this->objectIdName = 'orderId';
                $this->objectId = 12;
                break;
            case 88:
            case 'projects':
                $this->objectSingularName = 'project';
                $this->objectUrlName = 'projects';
                $this->objectDataName = 'projectInformation';
                $this->objectIdName = 'projectId';
                $this->objectId = 88;
                break;
            case 160:
            case 'properties':
                $this->objectSingularName = 'property';
                $this->objectUrlName = 'properties';
                $this->objectDataName = 'propertyData';
                $this->objectIdName = 'propertyId';
                $this->objectId = 160;
                break;
            case 37:
            case 'suppliers':
                $this->objectSingularName = 'supplier';
                $this->objectUrlName = 'suppliers';
                $this->objectDataName = 'supplierData';
                $this->objectIdName = 'supplierId';
                $this->objectId = 37;
                break;
            case 19:
            case 'targets':
                $this->objectSingularName = 'target';
                $this->objectUrlName = 'targets';
                $this->objectDataName = 'targetIdx';
                $this->objectIdName = 'id';
                $this->objectId = 19;
            break;
            default:
                //For custom apps we should pass in customapp-appid
                if(strpos($appNameOrId,'customapp-') !== false) {
                    $this->objectSingularName = 'customapp';
                    $this->objectUrlName = 'customapp';
                    $this->objectDataName = 'customAppData';
                    $this->objectIdName = 'customAppId';
                    $this->objectId = $appId;
                }else{
                    //If we couldn't resolve to any configuration we need to throw an exception
                    throw new Exception('AppParams unable to identify app parameters for provided $appNameOrId value ('.$appNameOrId.')');
                }
        }
    }
}