<?php

/**
 * @link https://github.com/silverstripe/silverstripe-subsites/issues/22
 * @package subsites
 * @description replaces standard auto-complete for members table in security section of CMS
 */
class SubsiteSecurityAutocompleter extends Controller
{

    function init()
    {
        SSViewer::set_source_file_comments(false);
        parent::init();
    }

    /**
     * Ajax autocompletion
     */
    public function autocomplete()
    {

        $fieldName = $this->urlParams['ID'];
        $fieldVal  = $_REQUEST[$fieldName];
        $result    = '';

        // Make sure we only autocomplete on keys that actually exist, and that we don't autocomplete on password
        if (!singleton('Member')->hasDatabaseField($fieldName) || $fieldName == 'Password')
                return;

        $groups      = DataObject::get("Group");
        $groupIDs    = array();
        $groupIDs[0] = 0;
        foreach ($groups as $group) {
            if ($group->canEdit()) $groupIDs[$group->ID] = $group->ID;
        }
        $idList  = implode(",", $groupIDs);
        $where   = ' AND `Group_Members`.`GroupID` IN ('.$idList.') ';
        $matches = DataObject::get("Member",
                "`$fieldName` LIKE '".Convert::raw2sql($fieldVal)."%' ".$where,
                $orderBy = '`'.$fieldName.'` ASC',
                ' INNER JOIN `Group_Members` ON `Group_Members`.`MemberID` = `Member`.`ID`');
        if ($matches->count() > 0) {
            $result .= "<ul>";
            foreach ($matches as $match) {
                if (!$match->canView()) {
                    continue;
                }
                $data = $match->FirstName;
                $data .= ",$match->Surname";
                $data .= ",$match->Email";
                $result .= "<li>".$match->$fieldName."<span class=\"informal\">($match->FirstName $match->Surname, $match->Email)</span><span class=\"informal data\">$data</span></li>";
            }
            $result .= "</ul>";
        }
        return $result;
    }
}