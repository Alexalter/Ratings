<?php
/**
 * Zikula Application Framework
 *
 * @copyright (c) 2001, Zikula Development Team
 * @link http://www.zikula.org
 * @version $Id$
 * @license GNU/GPL - http://www.gnu.org/copyleft/gpl.html
*/

/**
 * Log a vote and display the results form
 *
 * @author Mark West
 * @param pollid the poll to vote on
 * @param voteid the option to vote on
 * @return string updated display for the block
 */
function ratings_ajax_rate()
{
    $modname =    DataUtil::convertFromUTF8(FormUtil::getPassedValue('modname', null, 'POST'));
    $objectid =   DataUtil::convertFromUTF8(FormUtil::getPassedValue('objectid', null, 'POST'));
    $rating =     DataUtil::convertFromUTF8(FormUtil::getPassedValue('rating', null, 'POST'));
    $ratingtype = DataUtil::convertFromUTF8(FormUtil::getPassedValue('ratingtype', ModUtil::getVar('Ratings', 'defaultstyle'), 'POST'));
    $returnurl =  DataUtil::convertFromUTF8(FormUtil::getPassedValue('returnurl', null, 'POST'));

    if (!SecurityUtil::checkPermission('Ratings::', "$modname:$ratingtype:$objectid", ACCESS_COMMENT)) {
        AjaxUtil::error($this->__('Sorry! No authorization to access this module.'));
    }

    // log rating of item
    $newrating = ModUtil::apiFunc('Ratings', 'user', 'rate',
                              array('modname'    => $modname,
                                    'objectid'   => $objectid,
                                    'ratingtype' => $ratingtype,
                                    'rating'     => $rating));

    // get the new output
    $result = ModUtil::func('Ratings', 'user', 'display',
                        array('objectid'  => $objectid,
                              'extrainfo' => array('module'     => $modname,
                                                   'returnurl'  => $returnurl)));


    // return the new content for the block
    return array('result' => $result);
}
