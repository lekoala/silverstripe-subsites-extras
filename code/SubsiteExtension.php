<?php

/**
 * SubsiteExtension
 * - Create a default administrator group for the subsite on creation
 *
 * @author lekoala
 */
class SubsiteExtension extends DataExtension
{
    private static $db                        = array(
        'BaseFolder' => 'Varchar(50)'
    );
    private static $admin_default_permissions = array();

    function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // create the base folder
        if (!$this->owner->BaseFolder && $this->owner->Title) {
            $filter                  = new URLSegmentFilter();
            $this->owner->BaseFolder = $filter->filter($this->owner->getTitle());
            $this->owner->BaseFolder = str_replace(' ','',ucwords(str_replace('-', ' ', $this->owner->BaseFolder)));
        }
    }

    function onAfterWrite()
    {
        parent::onAfterWrite();


        if (!$this->owner->ID) {
            return;
        }

        //make sure we have a group for this subsite
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

    function updateCMSFields(\FieldList $fields)
    {
        $fields->addFieldToTab('Root.Configuration', new TextField('BaseFolder'));
    }

    function getAdministratorGroupName()
    {
        return 'Administrators '.$this->owner->Title;
    }
}