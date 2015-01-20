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
    private static $_current_siteconfig_cache = array();

    function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Create the base folder
        if (!$this->owner->BaseFolder && $this->owner->Title) {
            $filter                  = new URLSegmentFilter();
            $this->owner->BaseFolder = $filter->filter($this->owner->getTitle());
            $this->owner->BaseFolder = str_replace(' ', '',
                ucwords(str_replace('-', ' ', $this->owner->BaseFolder)));
        }

        // If name has changed, rename existing groups
        $changes = $this->owner->getChangedFields();
        if (isset($changes['Title']) && !empty($changes['Title']['before'])) {
            $filter    = new URLSegmentFilter();
            $groupName = $this->getAdministratorGroupName($changes['Title']['before']);
            $group     = $this->getGroupByName($groupName);
            if ($group) {
                $group->Title = $this->getAdministratorGroupName($changes['Title']['after']);
                $group->Code  = $filter->filter($group->Title);
                $group->write();
            }
            $membersGroupName = $this->getMembersGroupName($changes['Title']['before']);
            $membersGroup     = $this->getGroupByName($membersGroupName);
            if ($membersGroup) {
                $membersGroup->Title = $this->getMembersGroupName($changes['Title']['after']);
                $membersGroup->Code  = $filter->filter($membersGroup->Title);
                $membersGroup->write();
            }
        }
    }

    function onAfterWrite()
    {
        parent::onAfterWrite();

        // TODO: should test if this is needed or not
        if (!$this->owner->ID) {
            return;
        }

        // Apply the subsite title to config
        $siteconfig = $this->getSiteConfig();
        if ($siteconfig) {
            if ($siteconfig->Title == _t('Subsite.SiteConfigTitle',
                    'Your Site Name') && $this->owner->Title) {
                $siteconfig->Title = $this->owner->Title;
                $siteconfig->write();
            }
        }

        // Make sure we have groups for this subsite
        $groupName = $this->getAdministratorGroupName();
        $group     = $this->getGroupByName($groupName);
        if ($groupName && !$group) {
            $group                    = new Group();
            $group->Title             = $groupName;
            $group->AccessAllSubsites = true;
            $group->write();

            $group->Subsites()->add($this->owner);

            // Apply default permissions to this group
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

        $membersGroupName = $this->getMembersGroupName();
        $membersGroup     = $this->getGroupByName($membersGroupName);
        if ($membersGroupName && !$membersGroup) {
            $membersGroup        = new Group();
            $membersGroup->Title = $membersGroupName;
            $membersGroup->write();

            $membersGroup->Subsites()->add($this->owner);
            $membersGroup->write();
        }
    }

    function updateCMSFields(\FieldList $fields)
    {
        $fields->addFieldToTab('Root.Configuration', new TextField('BaseFolder'));
    }

    /**
     * @param string $name
     * @return Group
     */
    function getGroupByName($name)
    {
        if (!$name) {
            return false;
        }
        $urlfilter = new URLSegmentFilter;
        return Group::get()->filter('Code', $urlfilter->filter($name))->first();
    }

    /**
     * Get the administrator group name based on subsite Title
     *
     * @param string $title
     * @return string
     */
    function getAdministratorGroupName($title = null)
    {
        if ($title === null) {
            $title = $this->owner->Title;
        }
        if (!$title) {
            return;
        }
        return 'Administrators '.$title;
    }

    /**
     * Get the members group name based on subsite Title
     *
     * @param string $title
     * @return string
     */
    function getMembersGroupName($title = null)
    {
        if ($title === null) {
            $title = $this->owner->Title;
        }
        if (!$title) {
            return;
        }
        return 'Members '.$title;
    }

    /**
     * Return a siteconfig for this subsite
     * 
     * @return \SiteConfig
     */
    function getSiteConfig()
    {
        if (!$this->owner->ID) {
            return;
        }
        if (isset(self::$_current_siteconfig_cache[$this->owner->ID])) {
            return self::$_current_siteconfig_cache[$this->owner->ID];
        }
        Subsite::$disable_subsite_filter = true;
        $sc                              = SiteConfig::get()->filter('SubsiteID',
                $this->owner->ID)->first();
        Subsite::$disable_subsite_filter = false;

        if (!$sc) {
            $sc            = new SiteConfig();
            $sc->SubsiteID = $this->owner->ID;
            $sc->Title     = _t('Subsite.SiteConfigTitle', 'Your Site Name');
            $sc->Tagline   = _t('Subsite.SiteConfigSubtitle',
                'Your tagline here');
            $sc->write();
        }

        self::$_current_siteconfig_cache[$this->owner->ID] = $sc;
        return $sc;
    }
}