<?php
/**
 * Ratings
 *
 * @copyright (c) 2002, Zikula Development Team
 * @link      http://code.zikula.org/ratings/
 * @version   $Id$
 * @license   GNU/GPL - http://www.gnu.org/copyleft/gpl.html
 */

/**
 * Get a rating for a specific item
 * @author Jim McDonald
 * @param $args['modname'] name of the module this rating is for
 * @param $args['objectid'] ID of the item this rating is for
 * @param $args['rid'] ID of the rating
 *
 * This API requires either (modname and objectid) or rid
 *
 * @return int rating the corresponding rating, or void if no rating exists
 */
function ratings_userapi_get($args)
{
    $dom = ZLanguage::getModuleDomain('Ratings');
    // Argument check
    if ((!isset($args['modname']) || !isset($args['objectid']))
        && !isset($args['rid'])) {
        return LogUtil::registerArgsError();
    }

    $permFilter = array(array('realm'           => 0,
                              'component_left'  => 'Ratings',
                              'instance_left'   => 'module',
                              'instance_middle' => '',
                              'instance_right'  => 'itemid',
                              'level'           => ACCESS_READ));

    if (isset($args['modname']) && isset($args['objectid'])) {
        // Database information
        $pntable = pnDBGetTables();
        $ratingscolumn = $pntable['ratings_column'];

        // form the where clause
        $where = 'WHERE '.$ratingscolumn['module'].' = "'.DataUtil::formatForStore($args['modname']).'"'.
                 'AND '.$ratingscolumn['itemid'].' = "'.DataUtil::formatForStore($args['objectid']).'"';

        $ratings = DBUtil::selectObjectArray('ratings', $where, 'rid', 1, -1, '', $permFilter);
        if (isset($ratings[0])) {
            return $ratings[0];
        }
    } else if (isset($args['rid'])) {
        return DBUtil::selectObjectByID('ratings', $args['rid'], 'rid', '', $permFilter);
    }

    return false;
}


/**
 * get all ratings for a given module
 * @author Mark West
 * @param $args['modname'] name of the module this rating is for
 * @param $args['sortby'] column to sort by (optional)
 * @param $args['numitems'] number of items to return (optional)
 * @return mixed array of ratings or false
 */
function ratings_userapi_getall($args)
{
    $dom = ZLanguage::getModuleDomain('Ratings');

    if (!isset($args['modname'])) {
        $args['modname'] = null;
    }

    $items = array();

    // Security check
    if (!SecurityUtil::checkPermission('Ratings::', "$args[modname]::", ACCESS_READ)) {
        return $items;
    }

    // Database information
    $pntable       = pnDBGetTables();
    $ratingscolumn = $pntable['ratings_column'];

    // set a default for the collateral clause
    if (!isset($args['cclause']) || is_numeric($args['cclause']) || $args['cclause'] != 'ASC') {
        $args['cclause'] = 'DESC';
    }

    // form where clause
    $whereargs = array();
    if (isset($args['modname'])) {
        $whereargs[] = "$ratingscolumn[module] = '" . DataUtil::formatForStore($args['modname']) . "'";
    }
    $where = null;
    if (count($whereargs) > 0) {
        $where = ' WHERE ' . implode(' AND ', $whereargs);
    }

    // form order by clause
    if (isset($args['sortby'])) {
        $sortstring = " ORDER BY " . $ratingscolumn[$args['sortby']] . " " . $args['cclause'];
    } else {
        $sortstring = '';
    }

    $numitems = (isset($args['numitems']) && is_numeric($args['numitems'])) ? $args['numitems'] : -1;

    // define the permissions filter to apply
    $permFilter = array();
    $permFilter[] = array('realm'            => 0,
                          'component_left'   => 'Ratings',
                          'component_middle' => '',
                          'component_right'  => '',
                          'instance_left'    => 'module',
                          'instance_middle'  => '',
                          'instance_right'   => 'itemid',
                          'level'            => ACCESS_OVERVIEW);

    $items = DBUtil::selectObjectArray('ratings', $where, $sortstring, $limitOffset=-1, $numitems, '', $permFilter);

    // Check for an error with the database code, and if so set an appropriate error message and return
    if ($items === false) {
        return LogUtil::registerError(__('Error! Could not load items.', $dom));
    }

    // Return the items
    return $items;
}

/**
 * utility function to count the number of items held by this module
 * @return integer number of items held by this module
 */
function ratings_userapi_countitems($args)
{
    // Return the number of items
    return DBUtil::selectObjectCount('ratings', '', 'rid');
}

/**
 * Rate an item
 * @author Jim McDonald
 * @param $args['modname'] module name of the item to rate
 * @param $args['id'] ID of the item to rate
 * @param $args['rating'] actual rating
 * @return int the new rating for this item
 */
function ratings_userapi_rate($args)
{
    $dom = ZLanguage::getModuleDomain('Ratings');
    // Argument check
    if ((!isset($args['modname'])) ||
        (!isset($args['objectid'])) ||
        (!isset($args['rating']))) {
        return LogUtil::registerArgsError();
    }

    // Security check
    if (!SecurityUtil::checkPermission('Ratings::', "$args[modname]::$args[objectid]", ACCESS_READ)) {
        return LogUtil::registerPermissionError();
    }

    // Database information
    $pntable          = pnDBGetTables();
    $ratingscolumn    = $pntable['ratings_column'];
    $ratingslogcolumn = $pntable['ratingslog_column'];

    // Get the user id an ip
    $logid = pnUserGetVar('uid');
    $logip = pnServerGetVar('REMOTE_ADDR');

    // Whether the member has already voted for this item for future check
    $where = '(' . $ratingslogcolumn['userid'] . ' = "' . DataUtil::formatForStore($logid) . '"'
            .' OR ' . $ratingslogcolumn['userid'] . ' = "' . DataUtil::formatForStore($logip) . '")'
            .' AND ' . $ratingslogcolumn['ratingid'] . ' = "' . $args['modname'] . $args['objectid'] . '"';
    $alreadyReg = DBUtil::selectFieldArray('ratingslog', 'userid', $where, '');

    // Multiple rate check
    $seclevel = pnModGetVar('Ratings', 'seclevel');
    if ($seclevel == 'high' && $alreadyReg) {
        // Check against database to see if user has already voted
        return false;
    }
    elseif ($seclevel == 'medium')
    {
        // Check against session to see if user has voted recently
        if (SessionUtil::getVar("Rated" . $args['modname'] . $args['objectid'])) {
            return false;
        }
    }

    // Check our input
    if ($args['rating'] < 0 || $args['rating'] > 100) {
        return LogUtil::registerError(__('Error! The rating value is not correct.', $dom));
    }

    // Get the current values for the current module and itemid into the database
    $where = $ratingscolumn['module'] . ' = "' . DataUtil::formatForStore($args['modname']) . '"'
            .' AND ' . $ratingscolumn['itemid'] . ' = "' . DataUtil::formatForStore($args['objectid']) . '"';
    $rating = DBUtil::selectObject('ratings', $where);
    if ($rating === false) {
        return LogUtil::registerError(__('Error! Could not load items.', $dom));
    }

    // Save the new rate into the database
    if ($rating) {
        // Calculate new rating
        $rating['numratings']++;
        $rating['rating'] = (int)((($rating['rating']*($rating['numratings'] - 1)) + $args['rating']) / $rating['numratings']);

        $res = DBUtil::updateObject($rating, 'ratings', '', 'rid');
        if ($res === false) {
            return LogUtil::registerError (__('Error! Update attempt failed.', $dom));
        }
    } else {
        $rating = array();
        $rating['module']     = $args['modname'];
        $rating['itemid']     = $args['objectid'];
        $rating['rating']     = $args['rating'];
        $rating['numratings'] = 1;

        $res = DBUtil::insertObject($rating, 'ratings', 'rid');
        if ($res === false) {
            return LogUtil::registerError(__('Error! Save attempt failed.', $dom));
        }
    }

    // Set note that user has rated this item
    if (!$alreadyReg) {
        $ratinglog = array();
        $ratinglog['userid']   = (pnUserLoggedIn()) ? $logid : $logip;
        $ratinglog['ratingid'] = $args['modname'] . $args['objectid'];

        $res = DBUtil::insertObject($ratinglog, 'ratingslog', 'rid');
        if ($res === false) {
            return LogUtil::registerError (__('Error! Save log attempt failed.'));
        }
    }

    // Set session security against rate rigging for medium level
    if ($seclevel == 'medium') {
        SessionUtil::setVar("Rated" . $args['modname'] . $args['objectid'], true);
    }

    return $rating['rating'];
}
