<?php

/**
 * SubsiteExtension
 * - Create a default administrator group for the subsite on creation
 *
 * @author lekoala
 */
class SubsiteExtension extends DataExtension
{
    private static $admin_default_permissions = array();

    function onAfterWrite()
    {
        parent::onAfterWrite();


        //make sure we have a group for this subsite
        if ($this->owner->ID) {
            $groupName  = $this->getAdministratorGroupName();
            $groupCount = DB::query('SELECT * FROM Group_Subsites WHERE SubsiteID = '.$this->owner->ID)->numRecords();
            if (!$groupCount) {
                $group                    = new Group();
                $group->Title             = $groupName;
                $group->AccessAllSubsites = true;
                $group->write();

                $group->Subsites()->add($this->owner);

                //apply default permissions to this group
                $codes               = array_unique(array_keys(Permission::get_codes(false)));
                $default_permissions = Config::inst()->get('SubsiteExtension',
                    'admin_default_permissions');
                foreach ($default_permissions as $p) {
                    if (in_array($p, $codes)) {
                        $po = new Permission(array('Code' => $p));
                        $po->write();
                        $group->Permissions()->add($po);
                    }
                }

                $group->write();
            }
        }
    }

    function getAdministratorGroupName()
    {
        return 'Administrators '.$this->owner->Title;
    }
}