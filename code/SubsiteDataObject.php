<?php

/**
 * Extension for the DataObject object to add subsites support
 *
 * All objects are visible on main site by default, except if HideOnMainSite is checked
 * Please note this applies to ModelAdmin as well (you need to filter things if you want
 * to display only records in the main site)
 *
 * @author lekoala
 */
class SubsiteDataObject extends DataExtension
{
    private static $_accessible_sites_map_cache = null;
    private static $_accessible_permissions     = null;
    private static $db                          = array(
        'HideOnMainSite' => 'Boolean',
    );
    private static $has_one                     = array(
        'Subsite' => 'Subsite',
    );

    public function isMainDataObject()
    {
        if ($this->owner->SubsiteID == 0) {
            return true;
        }
        return false;
    }

    public function canView($member = null)
    {
        if ($this->canEdit($member)) {
            return true;
        }
    }

    /**
     * Update any requests to limit the results to the current site
     */
    public function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null)
    {
        $ctrl = null;
        if (Controller::has_curr()) {
            $ctrl = Controller::curr();
        }

        if (Subsite::$disable_subsite_filter) {
            return;
        }
        if ($dataQuery && $dataQuery->getQueryParam('Subsite.filter') === false) {
            return;
        }
        if ($ctrl && get_class(Controller::curr()) == 'Security') {
            return;
        }

        // Don't run on delete queries, since they are always tied to
        // a specific ID.
        if ($query->getDelete()) {
            return;
        }

        // If you're querying by ID, ignore the sub-site - this is a bit ugly...
        // if(!$query->where || (strpos($query->where[0], ".\"ID\" = ") === false && strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false && strpos($query->where[0], "ID = ") !== 0)) {
        if (!$query->filtersOnID()) {
            if (Subsite::$force_subsite) {
                $subsiteID = Subsite::$force_subsite;
            } else {
                /* if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
                  else */$subsiteID = (int) Subsite::currentSubsiteID();
            }

            $froms     = $query->getFrom();
            $froms     = array_keys($froms);
            $tableName = array_shift($froms);

            if ($subsiteID != 0) {
                $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
            } else {
                $query->addWhere("\"$tableName\".\"HideOnMainSite\" = 0");
            }
        }
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->ID && !$this->owner->SubsiteID) {
            $this->owner->SubsiteID = Subsite::currentSubsiteID();
        }

        parent::onBeforeWrite();
    }

    public static function accessible_sites_map($refresh = false)
    {
        if (!$refresh && self::$_accessible_sites_map_cache) {
            return self::$_accessible_sites_map_cache;
        }
        $subsites    = Subsite::accessible_sites(self::accessiblePermissions());
        $subsitesMap = array();
        if ($subsites && $subsites->Count()) {
            $subsitesMap = $subsites->map('ID', 'Title');
        }
        self::$_accessible_sites_map_cache = $subsitesMap;
        return self::$_accessible_sites_map_cache;
    }

    public static function accessible_sites_ids($refresh = false)
    {
        $map = self::accessible_sites_map($refresh);
        return array_keys($map);
    }

    public static function check_accessible_sites_map($subsiteID,
                                                      $refresh = false)
    {
        if (!$subsiteID) {
            return false;
        }
        $array = self::accessible_sites_map($refresh);
        return array_key_exists($subsiteID, $array);
    }

    public function updateCMSFields(FieldList $fields)
    {
        $subsites    = Subsite::accessible_sites(self::accessiblePermissions());
        $subsitesMap = array();
        if ($subsites && $subsites->Count()) {
            $subsitesMap = $subsites->map('ID', 'Title');
        }
        if (!isset($subsitesMap[$this->owner->SubsiteID])) {
            $subsitesMap[$this->owner->SubsiteID] = $this->owner->Subsite()->Title;
        }
        if (Subsite::currentSubsiteID()) {
            $fields->removeByName('SubsiteID');
            $fields->push(new HiddenField('SubsiteID', null,
                Subsite::currentSubsiteID()));
            $fields->addFieldToTab('Root.Main',
                new CheckboxField('HideOnMainSite',
                _t('SubsitesExtra.HideOnMainSite', 'Hide on main site')));
        } else {
            $fields->removeByName('SubsiteID');
            $fields->addFieldToTab('Root.Subsite',
                new DropdownField('SubsiteID',
                _t('SubsitesExtra.Subsite', 'Subsite'), $subsitesMap));
            $fields->addFieldToTab('Root.Subsite',
                new CheckboxField('HideOnMainSite',
                _t('SubsitesExtra.HideOnMainSite', 'Hide on main site')));
        }

        // Profile integration
        SubsiteProfile::applyToFields($this->owner, $fields);
    }

    public function alternateSiteConfig()
    {
        if (!$this->owner->SubsiteID) {
            return false;
        }
        $sc = DataObject::get_one('SiteConfig',
                '"SubsiteID" = '.$this->owner->SubsiteID);
        if (!$sc) {
            $sc            = new SiteConfig();
            $sc->SubsiteID = $this->owner->SubsiteID;
            $sc->Title     = _t('Subsite.SiteConfigTitle', 'Your Site Name');
            $sc->Tagline   = _t('Subsite.SiteConfigSubtitle',
                'Your tagline here');
            $sc->write();
        }
        return $sc;
    }

    /**
     * Only allow editing of a page if the member satisfies one of the following conditions:
     * - Is in a group which has access to the subsite this page belongs to
     * - Is in a group with edit permissions on the "main site"
     *
     * @return boolean
     */
    public function canEdit($member = null)
    {
        // If no subsite ID is defined, let dataobject determine the permission
        if (!$this->owner->SubsiteID || !Subsite::currentSubsiteID()) {
            return null;
        }

        if (!is_null($this->owner->SubsiteID)) {
            $subsiteID = $this->owner->SubsiteID;
        } else {
            // The relationships might not be available during the record creation when using a GridField.
            // In this case the related objects will have empty fields, and SubsiteID will not be available.
            //
            // We do the second best: fetch the likely SubsiteID from the session. The drawback is this might
            // make it possible to force relations to point to other (forbidden) subsites.
            $subsiteID = Subsite::currentSubsiteID();
        }

        // If no subsite ID is defined, let dataobject determine the permission
        if (!$subsiteID) {
            return null;
        }

        if (!$member) {
            $member = Member::currentUser();
        }

        // Find the sites that this user has access to
        if ($member->ID == Member::currentUserID()) {
            $goodSites = self::accessible_sites_ids();
        } else {
            $goodSites = Subsite::accessible_sites(self::accessiblePermissions(),
                    true, 'all', $member)->column('ID');
        }
        // Return true if they have access to this object's site
        if (!(in_array(0, $goodSites) || in_array($subsiteID, $goodSites))) {
            return false;
        }
        return true;
    }

    public static function accessiblePermissions()
    {
        if (self::$_accessible_permissions === null) {
            self::$_accessible_permissions = Config::inst()->get('SubsiteExtension',
                'edit_permissions');
        }
        return self::$_accessible_permissions;
    }

    /**
     * @return boolean
     */
    public function canDelete($member = null)
    {
        if (!$member && $member !== false) {
            $member = Member::currentUser();
        }

        return $this->canEdit($member);
    }

    /**
     * Called by ContentController::init();
     */
    public static function contentcontrollerInit($controller)
    {
        $subsite = Subsite::currentSubsite();
        if ($subsite && $subsite->Theme) {
            SSViewer::set_theme(Subsite::currentSubsite()->Theme);
        }
    }

    public function alternateAbsoluteLink()
    {
        // Generate the existing absolute URL and replace the domain with the subsite domain.
        // This helps deal with Link() returning an absolute URL.
        $url = Director::absoluteURL($this->owner->Link());
        if ($this->owner->SubsiteID) {
            $url = preg_replace('/\/\/[^\/]+\//',
                '//'.$this->owner->Subsite()->domain().'/', $url);
        }
        return $url;
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     */
    public function cacheKeyComponent()
    {
        return 'subsite-'.Subsite::currentSubsiteID();
    }

    /**
     * @param Member
     * @return boolean|null
     */
    public function canCreate($member = null)
    {
        return true;
    }
}