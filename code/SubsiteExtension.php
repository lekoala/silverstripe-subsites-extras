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

        // Make sure we have a group for this subsite
        $groupName  = $this->getAdministratorGroupName();
        $groupCount = DB::query('SELECT * FROM Group_Subsites WHERE SubsiteID = '.$this->owner->ID)->numRecords();
        if (!$groupCount) {
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
    }

    function updateCMSFields(\FieldList $fields)
    {
        $fields->addFieldToTab('Root.Configuration', new TextField('BaseFolder'));
    }

    /**
     * Get the administrator group name based on subsite Title
     * 
     * @return string
     */
    function getAdministratorGroupName()
    {
        return 'Administrators '.$this->owner->Title;
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