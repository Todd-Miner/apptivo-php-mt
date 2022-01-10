<?php

declare(strict_types=1);

namespace ToddMinerTech\ApptivoPhp;

/**
 * Class AppParameters
 *
 * Class to map an app name/id to static values that are required for each app.  No raw source for these right now, manually tested and documented.
 *
 * @package ToddMinerTech\ApptivoPhp
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
            $matchVal = $this->appName;
        }else{
            $matchVal = $appNameOrId;
        }
        //Set the standard parameters to be used in the URL below
        switch(strtolower($matchVal)) {
            case 59:
            case 'cases':
            case 'case':
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
            case 'contact':
                $this->objectSingularName = 'contact';
                $this->objectUrlName = 'contacts';
                $this->objectDataName = 'contactData';
                $this->objectIdName = 'contactId';
                $this->objectId = 2;
                break;
            case 3:
            case 'customers':
            case 'customer':
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
                $this->objectId = intval($appNameOrIdParts[1]);
                break;
            case 8:
            case 'employees':
            case 'employee':
                $this->objectSingularName = 'employee';
                $this->objectUrlName = 'employees';
                $this->objectDataName = 'employeeData';
                $this->objectIdName = 'employeeId';
                $this->objectId = 8;
                break;
            case 155:
            case 'estimates':
            case 'estimate':
                $this->objectSingularName = 'estimate';
                $this->objectUrlName = 'estimates';
                $this->objectDataName = 'estimateData';
                $this->objectIdName = 'estimateId';
                $this->objectId = 155;
                break;
            case 33:
            case 'invoices':
            case 'invoice':
                $this->objectSingularName = 'invoice';
                $this->objectUrlName = 'invoice';
                $this->objectDataName = 'invoiceData';
                $this->objectIdName = 'invoiceId';
                $this->objectId = 33;
                break;
            case 13:
            case 'items':
            case 'item':
                $this->objectSingularName = 'item';
                $this->objectUrlName = 'items';
                $this->objectDataName = 'itemData';
                $this->objectIdName = 'itemId';
                $this->objectId = 13;
                break;
            case 4:
            case 'leads':
            case 'lead':
                $this->objectSingularName = 'lead';
                $this->objectUrlName = 'leads';
                $this->objectDataName = 'leadData';
                $this->objectIdName = 'leadId';
                $this->objectId = 4;
                break;
            case 11:
            case 'opportunities':
            case 'opportunity':
                $this->objectSingularName = 'opportunity';
                $this->objectUrlName = 'opportunities';
                $this->objectDataName = 'opportunityData';
                $this->objectIdName = 'opportunityId';
                $this->objectId = 11;
                break;
            case 12:
            case 'orders':
            case 'order':
                $this->objectSingularName = 'order';
                $this->objectUrlName = 'orders';
                $this->objectDataName = 'orderData';
                $this->objectIdName = 'orderId';
                $this->objectId = 12;
                break;
            case 88:
            case 'projects':
            case 'project':
                $this->objectSingularName = 'project';
                $this->objectUrlName = 'projects';
                $this->objectDataName = 'projectInformation';
                $this->objectIdName = 'projectId';
                $this->objectId = 88;
                break;
            case 160:
            case 'properties':
            case 'property':
                $this->objectSingularName = 'property';
                $this->objectUrlName = 'properties';
                $this->objectDataName = 'propertyData';
                $this->objectIdName = 'propertyId';
                $this->objectId = 160;
                break;
            case 37:
            case 'suppliers':
            case 'supplier':
                $this->objectSingularName = 'supplier';
                $this->objectUrlName = 'suppliers';
                $this->objectDataName = 'supplierData';
                $this->objectIdName = 'supplierId';
                $this->objectId = 37;
                break;
            case 19:
            case 'targets':
            case 'target':
                $this->objectSingularName = 'target';
                $this->objectUrlName = 'targets';
                $this->objectDataName = 'targetIdx';
                $this->objectIdName = 'id';
                $this->objectId = 19;
            break;
            default:
                throw new Exception('Invalid appNameOrId ('.$appNameOrId.')');
                
        }
    }
}
