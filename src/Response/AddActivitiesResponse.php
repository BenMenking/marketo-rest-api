<?php

/*
 * This file is part of the Marketo REST API Client package.
 *
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CSD\Marketo\Response;

use CSD\Marketo\Response;

/**
 * Response for the getLead and getLeadByFilterType API method.
 *
 * @author Daniel Chesterton <daniel@chestertondevelopment.com>
 */
class AddActivitiesResponse extends Response
{
	/**
     * Get the status of the custom activity. If no custom activity ID is given, it returns the status of the first one.
     *
     * @param int|null $id
     * @return bool
     */
    public function getStatus($id = null)
    {
        if ($this->isSuccess()) {
            if (is_null($id)) {
                $result = $this->getResult();
                return $result[0]['status'];
            }
            foreach ($this->getResult() as $row) {
                if ($row['id'] == $id) {
                    return $row['status'];
                }
            }
        }
        return false;
    }
}

