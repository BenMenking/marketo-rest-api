<?php
/*
 * This file is part of the Marketo REST API Client package.
 *
 * (c) 2014 Daniel Chesterton
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CSD\Marketo;

// Guzzle
use CommerceGuys\Guzzle\Plugin\Oauth2\Oauth2Plugin;
use CSD\Marketo\Response\GetLeadChanges;
use CSD\Marketo\Response\GetPagingToken;
use Guzzle\Common\Collection;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Description\ServiceDescription;

// Response classes
use CSD\Marketo\Response\AddOrRemoveLeadsToListResponse;
use CSD\Marketo\Response\AssociateLeadResponse;
use CSD\Marketo\Response\CreateOrUpdateLeadsResponse;
use CSD\Marketo\Response\GetCampaignResponse;
use CSD\Marketo\Response\GetCampaignsResponse;
use CSD\Marketo\Response\GetLeadResponse;
use CSD\Marketo\Response\GetLeadPartitionsResponse;
use CSD\Marketo\Response\GetLeadsResponse;
use CSD\Marketo\Response\GetListResponse;
use CSD\Marketo\Response\GetListsResponse;
use CSD\Marketo\Response\IsMemberOfListResponse;
use CSD\Marketo\Response\AddActivitiesResponse;
use CSD\Marketo\Response\MergeLeadResponse;

/**
 * Guzzle client for communicating with the Marketo.com REST API.
 *
 * @link http://developers.marketo.com/documentation/rest/
 *
 * @author Daniel Chesterton <daniel@chestertondevelopment.com>
 */
class Client extends GuzzleClient
{
    /**
     * {@inheritdoc}
     */
    public static function factory($config = array())
    {
        $default = array(
            'url' => false,
            'munchkin_id' => false,
            'version' => 1,
            'bulk' => false
        );

        $required = array('client_id', 'client_secret', 'version');
        $config = Collection::fromConfig($config, $default, $required);

        $url = $config->get('url');

        if (!$url) {
            $munchkin = $config->get('munchkin_id');

            if (!$munchkin) {
                throw new \Exception('Must provide either a URL or Munchkin code.');
            }

            $url = sprintf('https://%s.mktorest.com', $munchkin);
        }

        $grantType = new Credentials($url, $config->get('client_id'), $config->get('client_secret'));
        $auth = new Oauth2Plugin($grantType);

        if ($config->get('bulk') === true) {
            $restUrl = sprintf('%s/bulk/v%d', rtrim($url, '/'), $config->get('version'));
        } else {
            $restUrl = sprintf('%s/rest/v%d', rtrim($url, '/'), $config->get('version'));
        }

        $client = new self($restUrl, $config);
        $client->addSubscriber($auth);
        $client->setDescription(ServiceDescription::factory(__DIR__ . '/service.json'));
        $client->setDefaultOption('headers/Content-Type', 'application/json');

        return $client;
    }

    /**
     * Import Leads via file upload
     *
     * @param array $args - Must contain 'format' and 'file' keys
     *     e.g. array( 'format' => 'csv', 'file' => '/full/path/to/filename.csv'
     *
     * @link http://developers.marketo.com/documentation/rest/import-lead/
     *
     * @return Response/importLeadsCsv
     *
     * @throws \Exception
     */
    public function importLeadsCsv($args)
    {
        if (!is_readable($args['file'])) {
            throw new \Exception('Cannot read file: ' . $args['file']);
        }

        if (empty($args['format'])) {
            $args['format'] = 'csv';
        }

        return $this->getResult('importLeadsCsv', $args);
    }

    /**
     * Get status of an async Import Lead file upload
     *
     * @param int $batchId
     *
     * @link http://developers.marketo.com/documentation/rest/get-import-lead-status/
     *
     * @return Response/getBulkUploadStatus
     */
    public function getBulkUploadStatus($batchId)
    {
        if (empty($batchId) || !is_int($batchId)) {
            throw new \Exception('Invalid $batchId provided in ' . __METHOD__);
        }

        return $this->getResult('getBulkUploadStatus', array('batchId' => $batchId));
    }

    /**
     * Get failed lead results from an Import Lead file upload
     *
     * @param int $batchId
     *
     * @link http://developers.marketo.com/documentation/rest/get-import-failure-file/
     *
     * @return Response/getBulkUploadFailures
     */
    public function getBulkUploadFailures($batchId)
    {
        if( empty($batchId) || !is_int($batchId) ) {
            throw new \Exception('Invalid $batchId provided in ' . __METHOD__);
        }

        return $this->getResult('getBulkUploadFailures', array('batchId' => $batchId));
    }

    /**
     * Get warnings from Import Lead file upload
     *
     * @param int $batchId
     *
     * @link http://developers.marketo.com/documentation/rest/get-import-warning-file/
     *
     * @return Response/getBulkUploadWarnings
     */
    public function getBulkUploadWarnings($batchId)
    {
        if( empty($batchId) || !is_int($batchId) ) {
            throw new \Exception('Invalid $batchId provided in ' . __METHOD__);
        }

        return $this->getResult('getBulkUploadWarnings', array('batchId' => $batchId));
    }

    /**
     * Calls the CreateOrUpdateLeads command with the given action.
     *
     * @param string $action
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     *
     * @see Client::createLeads()
     * @see Client::createOrUpdateLeads()
     * @see Client::updateLeads()
     * @see Client::createDuplicateLeads()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return Response/CreateOrUpdateLeadsResponse
     */
    private function createOrUpdateLeadsCommand($action, $leads, $lookupField, $args, $returnRaw = true)
    {	

        $args['input'] = $leads;
        $args['action'] = $action;

        if (isset($lookupField)) {
            $args['lookupField'] = $lookupField;
        }

        return $this->getResult('createOrUpdateLeads', $args, false, $returnRaw);
    }
    
    /**
     * Calls the addOpportunities command with the given action.
     *
     * @param string $action
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     *
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return Response/CreateOrUpdateLeadsResponse
     */
    private function addOpportunitiesCommand($action, $leads, $lookupField, $args, $returnRaw = true)
    {	

        $args['input'] = $leads;
        $args['action'] = $action;

        if (isset($lookupField)) {
            $args['dedupeBy'] = $lookupField;
        }

        return $this->getResult('addOpportunities', $args, false, $returnRaw);
    }
    
    /**
     * Calls the addOpportunitiesRole command with the given action.
     *
     * @param string $action
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return Response/CreateOrUpdateLeadsResponse
     */
    private function addOpportunitiesRoleCommand($action, $leads, $lookupField, $args, $returnRaw = true)
    {	

        $args['input'] = $leads;
        $args['action'] = $action;

        if (isset($lookupField)) {
            $args['dedupeBy'] = $lookupField;
        }

        return $this->getResult('addOpportunitiesRole', $args, false, $returnRaw);
    }

    /**
     * Create the given leads.
     *
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     * @see Client::createOrUpdateLeadsCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return Response/CreateOrUpdateLeadsResponse
     */
    public function createLeads($leads, $lookupField = null, $args = array())
    {
        return $this->createOrUpdateLeadsCommand('createOnly', $leads, $lookupField, $args);
    }

    /**
     * Update the given leads, or create them if they do not exist.
     *
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     * @see Client::createOrUpdateLeadsCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return Response/CreateOrUpdateLeadsResponse
     */
    public function createOrUpdateLeads($leads, $lookupField = null, $args = array())
    {
        return $this->createOrUpdateLeadsCommand('createOrUpdate', $leads, $lookupField, $args);
    }

    /**
     * Update the given leads.
     *
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     * @see Client::createOrUpdateLeadsCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return Response/CreateOrUpdateLeadsResponse
     */
    public function updateLeads($leads, $lookupField = null, $args = array())
    {
        return $this->createOrUpdateLeadsCommand('updateOnly', $leads, $lookupField, $args);
    }

    /**
     * Create duplicates of the given leads.
     *
     * @param array  $leads
     * @param string $lookupField
     * @param array  $args
     * @see Client::createOrUpdateLeadsCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return Response/CreateOrUpdateLeadsResponse
     */
    public function createDuplicateLeads($leads, $lookupField = null, $args = array())
    {
        return $this->createOrUpdateLeadsCommand('createDuplicate', $leads, $lookupField, $args);
    }
    
    /**
     * Create duplicates of the given leads.
     *
     * @param array  $opportunities
     * @param string $lookupField
     * @param array  $args
     * @see Client::addOpportunitiesCommand()
     *
     * @link
     *
     * @return Response/addOpportunitiesResponse
     */
    public function addOpportunities($opportunities, $lookupField = 'externalOpportunityId', $args = array())
    {
        return $this->addOpportunitiesCommand('createOrUpdate', $opportunities, $lookupField, $args);
    }
    
    /**
     * Create duplicates of the given leads.
     *
     * @param array  $leads
     * @param array  $args
     * @see Client::addOpportunitiesRoleCommand()
     *
     * @link http://developers.marketo.com/documentation/rest/createupdate-leads/
     *
     * @return Response/CreateOrUpdateLeadsResponse
     */
    public function addOpportunitiesRole($leads, $lookupField = 'externalOpportunityId', $args = array())
    {
        return $this->addOpportunitiesRoleCommand('createOrUpdate', $leads, $lookupField, $args);
    }

    /**
     * Get multiple lists.
     *
     * @param int|array $ids  Filter by one or more IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-lists/
     *
     * @return Response/GetListsResponse
     */
    public function getLists($ids = null, $args = array(), $returnRaw = false)
    {
        if ($ids) {
            $args['id'] = $ids;
        }

        return $this->getResult('getLists', $args, is_array($ids), $returnRaw);
    }

    /**
     * Get a list by ID.
     *
     * @param int   $id
     * @param array $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-list-by-id/
     *
     * @return Response/GetListResponse
     */
    public function getList($id, $args = array(), $returnRaw = false)
    {
        $args['id'] = $id;

        return $this->getResult('getList', $args, false, $returnRaw);
    }

    /**
     * Get multiple leads by filter type.
     *
     * @param string $filterType   One of the supported filter types, e.g. id, cookie or email. See Marketo's documentation for all types.
     * @param string $filterValues Comma separated list of filter values
     * @param array  $fields       Array of field names to be returned in the response
     * @param string $nextPageToken
     * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/
     *
     * @return Response/GetLeadsResponse
     */
    public function getLeadsByFilterType($filterType, array $filterValues, $fields = array(), $nextPageToken = null, $returnRaw = true)
    {
        $args['filterType'] = $filterType;
        
		$memberIDs = array();
		$member = 0; 
		
		foreach($filterValues as $value) {
			$memberIDs[$member] = $value[$filterType];
			$member++;	
		}
        
        if (count($memberIDs)) {
            $args['filterValues'] = implode(',', $memberIDs);
        }

        if ($nextPageToken) {
            $args['nextPageToken'] = $nextPageToken;
        }

        if (count($fields)) {
            $args['fields'] = implode(',', $fields);
        }

        return $this->getResult('getLeadsByFilterType', $args, false, $returnRaw);
    }

    /**
     * Get a lead by filter type.
     *
     * Convenient method which uses {@link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/}
     * internally and just returns the first lead if there is one.
     *
     * @param string $filterType  One of the supported filter types, e.g. id, cookie or email. See Marketo's documentation for all types.
     * @param string $filterValue The value to filter by
     * @param array  $fields      Array of field names to be returned in the response
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-filter-type/
     *
     * @return Response/GetLeadResponse
     */
    public function getLeadByFilterType($filterType, $filterValue, $fields = array(), $returnRaw = false)
    {
        $args['filterType'] = $filterType;
        $args['filterValues'] = $filterValue;

        if (count($fields)) {
            $args['fields'] = implode(',', $fields);
        }

        return $this->getResult('getLeadByFilterType', $args, false, $returnRaw);
    }

    /**
     * Get lead partitions.
     *
     * @link http://developers.marketo.com/documentation/rest/get-lead-partitions/
     *
     * @return Response/GetLeadPartitionsResponse
     */
    public function getLeadPartitions($args = array(), $returnRaw = false)
    {
        return $this->getResult('getLeadPartitions', $args, false, $returnRaw);
    }

    /**
     * Get multiple leads by list ID.
     *
     * @param int   $listId
     * @param array $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-leads-by-list-id/
     *
     * @return Response/GetLeadsResponse
     */
    public function getLeadsByList($listId, $args = array(), $returnRaw = false)
    {
        $args['listId'] = $listId;

        return $this->getResult('getLeadsByList', $args, false, $returnRaw);
    }

    /**
     * Get a lead by ID.
     *
     * @param int   $id
     * @param array $fields
     * @param array $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-lead-by-id/
     *
     * @return Response/GetLeadResponse
     */
    public function getLead($id, $fields = null, $args = array(), $returnRaw = false)
    {
        $args['id'] = $id;

        if (is_array($fields)) {
            $args['fields'] = implode(',', $fields);
        }

        return $this->getResult('getLead', $args, false, $returnRaw);
    }

    /**
     * Check if a lead is a member of a list.
     *
     * @param int       $listId List ID
     * @param int|array $id     Lead ID or an array of Lead IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/member-of-list/
     *
     * @return Response/IsMemberOfListResponse
     */
    public function isMemberOfList($listId, $id, $args = array(), $returnRaw = false)
    {
        $args['listId'] = $listId;
        $args['id'] = $id;

        return $this->getResult('isMemberOfList', $args, is_array($id), $returnRaw);
    }

    /**
     * Get a campaign by ID.
     *
     * @param int   $id
     * @param array $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-campaign-by-id/
     *
     * @return Response/GetCampaignResponse
     */
    public function getCampaign($id, $args = array(), $returnRaw = false)
    {
        $args['id'] = $id;

        return $this->getResult('getCampaign', $args, false, $returnRaw);
    }

    /**
     * Get campaigns.
     *
     * @param int|array $ids  A single Campaign ID or an array of Campaign IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/get-multiple-campaigns/
     *
     * @return Response/GetCampaignsResponse
     */
    public function getCampaigns($ids = null, $args = array(), $returnRaw = false)
    {
        if ($ids) {
            $args['id'] = $ids;
        }

        return $this->getResult('getCampaigns', $args, is_array($ids), $returnRaw);
    }

    /**
     * Add one or more leads to the specified list.
     *
     * @param int       $listId List ID
     * @param int|array $leads  Either a single lead ID or an array of lead IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/add-leads-to-list/
     *
     * @return Response/addLeadsToList
     */
    public function addLeadsToList($listId, $leads, $args = array(), $returnRaw = false)
    {
        $args['listId'] = $listId;
        $args['id'] = (array) $leads;

        return $this->getResult('addLeadsToList', $args, true, $returnRaw);
    }

    /**
     * Remove one or more leads from the specified list.
     *
     * @param int       $listId List ID
     * @param int|array $leads  Either a single lead ID or an array of lead IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/remove-leads-from-list/
     *
     * @return Response/AddOrRemoveLeadsToListResponse
     */
    public function removeLeadsFromList($listId, $leads, $args = array(), $returnRaw = false)
    {
        $args['listId'] = $listId;
        $args['id'] = (array) $leads;

        return $this->getResult('removeLeadsFromList', $args, true, $returnRaw);
    }

    /**
     * Delete one or more leads
     *
     * @param int|array $leads  Either a single lead ID or an array of lead IDs
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/delete-lead/
     *
     * @return Response/DeleteLeadResponse
     */
    public function deleteLead($leads, $args = array(), $returnRaw = false)
    {
        $args['id'] = (array) $leads;

        return $this->getResult('deleteLead', $args, true, $returnRaw);
    }

    /**
     * Trigger a campaign for one or more leads.
     *
     * @param int       $id     Campaign ID
     * @param int|array $leads  Either a single lead ID or an array of lead IDs
     * @param array     $tokens Key value array of tokens to send new values for.
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/request-campaign/
     *
     * @return Response/requestCampaign
     */
    public function requestCampaign($id, $leads, $tokens = array(), $args = array(), $returnRaw = false)
    {
        $args['id'] = $id;

        $args['input'] = array('leads' => array_map(function ($id) {
            return array('id' => $id);
        }, (array) $leads));

        if (!empty($tokens)) {
            $args['input']['tokens'] = $tokens;
        }

        return $this->getResult('requestCampaign', $args, false, $returnRaw);
    }

    /**
     * Schedule a campaign
     *
     * @param int         $id      Campaign ID
     * @param DateTime    $runAt   The time to run the campaign. If not provided, campaign will be run in 5 minutes.
     * @param array       $tokens  Key value array of tokens to send new values for.
     * @param array       $args
     *
     * @link http://developers.marketo.com/documentation/rest/schedule-campaign/
     *
     * @return Response/scheduleCampaign
     */
    public function scheduleCampaign($id, \DateTime $runAt = NULL, $tokens = array(), $args = array(), $returnRaw = false)
    {
        $args['id'] = $id;

        if (!empty($runAt)) {
          $args['input']['runAt'] = $runAt->format('c');
        }

        if (!empty($tokens)) {
            $args['input']['tokens'] = $tokens;
        }

        return $this->getResult('scheduleCampaign', $args, false, $returnRaw);
    }

    /**
     * Associate a lead
     *
     * @param int       $id
     * @param string    $cookie
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/rest/associate-lead/
     *
     * @return Response/associateLead
     */
    public function associateLead($id, $cookie = null, $args = array(), $returnRaw = false)
    {
        $args['id'] = $id;

        if (!empty($cookie)) {
            $args['cookie'] = $cookie;
        }

        return $this->getResult('associateLead', $args, false, $returnRaw);
    }

    /**
     * Get the paging token required for lead activity and changes
     *
     * @param string $sinceDatetime String containing a datetime
     * @param array  $args
     * @param bool   $returnRaw
     *
     * @return Response
     * @link http://developers.marketo.com/documentation/rest/get-paging-token/
     *
     */
    public function getPagingToken($sinceDatetime, $args = array(), $returnRaw = false)
    {
        $args['sinceDatetime'] = $sinceDatetime;

        return $this->getResult('getPagingToken', $args, false, $returnRaw);
    }

    /**
     * Get lead changes
     *
     * @param string       $nextPageToken Next page token
     * @param string|array $fields
     * @param array        $args
     * @param bool         $returnRaw
     *
     * @return Response/getLeadChanges
     * @link http://developers.marketo.com/documentation/rest/get-lead-changes/
     * @see  getPagingToken
     *
     */
    public function getLeadChanges($nextPageToken, $fields, $args = array(), $returnRaw = false)
    {
        $args['nextPageToken'] = $nextPageToken;
        $args['fields'] = (array) $fields;

        if (count($fields)) {
            $args['fields'] = implode(',', $fields);
        }

        return $this->getResult('getLeadChanges', $args, true, $returnRaw);
    }

    /**
     * Merge an Email
     *
     * @param int      $leadID
     * @param array    $leadIDs
     * @param array    $args
     *
     * @link http://developers.marketo.com/documentation/asset-api/approve-email-by-id/
     *
     * @return Response/mergeLead
     */
    public function mergeLead($leadID, $leadIDs, $args = array(), $returnRaw = true)
    {
        $args['id'] = implode(",", $leadID);
        
        if(count($leadIDs > 1)) {
	        $args['leadIds'] = implode(",", $leadIDs);	
        } else {
	        $args['leadId'] = implode(",", $leadIDs);
        }
        
        return $this->getResult('mergeLead', $args, false, $returnRaw);
    }

    /**
     * Update an editable section in an email
     *
     * @param int       $emailId
     * @param string    $htmlId
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/asset-api/update-email-content-by-id/
     *
     * @return Response/updateEmailContent
     */
    public function updateEmailContent($emailId, $args = array(), $returnRaw = false)
    {
        $args['id'] = $emailId;

        return $this->getResult('updateEmailContent', $args, false, $returnRaw);
    }

    /**
     * Update an editable section in an email
     *
     * @param int       $emailId
     * @param string    $htmlId
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/asset-api/update-email-content-in-editable-section/
     *
     * @return Response/updateEmailContentInEditableSection
     */
    public function updateEmailContentInEditableSection($emailId, $htmlId, $args = array(), $returnRaw = false)
    {
        $args['id'] = $emailId;
        $args['htmlId'] = $htmlId;

        return $this->getResult('updateEmailContentInEditableSection', $args, false, $returnRaw);
    }

    /**
     * Approve an email
     *
     * @param int       $emailId
     * @param string    $htmlId
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/asset-api/approve-email-by-id/
     *
     * @return Response/approveEmail
     */
    public function approveEmail($emailId, $args = array(), $returnRaw = false)
    {
        $args['id'] = $emailId;

        return $this->getResult('approveEmailbyId', $args, false, $returnRaw);
    }
    
    /**
     * Approve an email
     *
     * @param int       $emailId
     * @param string    $htmlId
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/asset-api/approve-email-by-id/
     * @special thanks https://github.com/marketo-api/marketo-rest-client/blob/
     * @return Response/addActivities
     */
    public function addActivities($activities, $args = array(), $returnRaw = true)
    {
        $args['input'] = [];
        foreach ($activities as $activity) {
            // Validation: Required parameters.
            foreach (['leadId', 'activityTypeId', 'primaryAttributeValue'] as $required) {
                if (!isset($activity[$required])) {
                    throw new \InvalidArgumentException("Required parameter \"{$required}\" is missing.");
                }
            }
            // Validation: Activity date is required by the API, but making it optional here, defaulting to now.
            if (!isset($activity['activityDate'])) {
                $activity['activityDate'] = new \DateTime();
            } elseif (!($activity['activityDate'] instanceof \DateTime)) {
                throw new \InvalidArgumentException('Required parameter "activityDate" must be a DateTime object.');
            }
            // Format required parameters
            $input = [
                'leadId' => (int) $activity['leadId'],
                'activityTypeId' => (int) $activity['activityTypeId'],
                'primaryAttributeValue' => (string) $activity['primaryAttributeValue'],
                'activityDate' => $activity['activityDate']->format('c'),
            ];
            // Optional parameters
            if (isset($activity['apiName'])) {
                $input['apiName'] = (string) $activity['apiName'];
            }
            if (isset($activity['status'])) {
                $input['status'] = (string) $activity['status'];
            }
            // The optional 'attributes' parameter has some validation.
            if (isset($activity['attributes'])) {
                if (!is_array($activity['attributes'])) {
                    throw new \InvalidArgumentException('Optional parameter "attributes" must be an array.');
                }
                $input['attributes'] = []; // Initialize
                foreach ($activity['attributes'] as $attribute) {
                    if (!is_array($attribute)) {
                        throw new \InvalidArgumentException('The "attributes" parameter must contain child array(s).');
                    }
                    // Required child parameters
                    foreach (['name', 'value'] as $required) {
                        if (!isset($attribute[$required])) {
                            throw new \InvalidArgumentException("Required array key \"{$required}\" is missing in the \"attributes\" parameter.");
                        }
                    }
                    if($attribute['name'] == "Tax Deductible" || $attribute['name'] == "Anonymous" || $attribute['name'] == "Initial Deposit Date") {
	                    $inputAttribute = [
	                        'name' => (string) $attribute['name'],
	                        'value' => $attribute['value'],
	                    ];
                    } else {
	                	$inputAttribute = [
	                        'name' => (string) $attribute['name'],
	                        'value' => (string) $attribute['value'],
	                    ];
	                }
                    // Optional child parameters
                    if (isset($attribute['apiName'])) {
                        $inputAttribute['apiName'] = (string) $attribute['apiName'];
                    }
                    $input['attributes'][] = $inputAttribute;
                }
            }
            $args['input'][] = $input;
 
        }
        return $this->getResult('addActivities', $args, false, $returnRaw);
    }
    
    /**
     * Approve an email
     *
     * @param int       $emailId
     * @param string    $htmlId
     * @param array     $args
     *
     * @link http://developers.marketo.com/documentation/asset-api/approve-email-by-id/
     *
     * @return Response/getActivities
     */
    public function getActivities($activityID, $args = array(), $returnRaw = false)
    {
        $args['id'] = $activityID;

        return $this->getResult('getActivityTypes', $args, false, $returnRaw);
    }

    /**
     * Internal helper method to actually perform command.
     *
     * @param string $command
     * @param array  $args
     * @param bool   $fixArgs
     *
     * @return Response/getResult
     */
    private function getResult($command, $args, $fixArgs = false, $returnRaw = false)
    {
        $cmd = $this->getCommand($command, $args);

        // Marketo expects parameter arrays in the format id=1&id=2, Guzzle formats them as id[0]=1&id[1]=2.
        // Use a quick regex to fix it where necessary.
        if ($fixArgs) {
            $cmd->prepare();

            $url = preg_replace('/id%5B([0-9]+)%5D/', 'id', $cmd->getRequest()->getUrl());
            $cmd->getRequest()->setUrl($url);
        }
        
        $cmd->prepare();

        if ($returnRaw) {
            return $cmd->getResponse()->getBody(true);
        }

        return $cmd->getResult();
    }

}
