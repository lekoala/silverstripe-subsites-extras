<?php

/**
 * Extension for the DataObject object to add subsites support
 *
 * @author lekoala
 */
class SubsiteDataobject extends DataExtension
{
    private static $_accessible_sites_map_cache = null;
    private static $has_one                     = array(
        'Subsite' => 'Subsite',
    );

    function isMainDataObject()
    {
        if ($this->owner->SubsiteID == 0) return true;
        return false;
    }

    function canView($member = null)
    {
        if ($this->canEdit($member)) {
            return true;
        }
    }

    /**
     * Update any requests to limit the results to the current site
     */
    function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null)
    {
        if (Subsite::$disable_subsite_filter) return;
        if ($dataQuery->getQueryParam('Subsite.filter') === false) return;
        if (get_class(Controller::curr()) == 'Security') return;

        // Don't run on delete queries, since they are always tied to
        // a specific ID.
        if ($query->getDelete()) return;

        // If you're querying by ID, ignore the sub-site - this is a bit ugly...
        // if(!$query->where || (strpos($query->where[0], ".\"ID\" = ") === false && strpos($query->where[0], ".`ID` = ") === false && strpos($query->where[0], ".ID = ") === false && strpos($query->where[0], "ID = ") !== 0)) {
        if (!$query->filtersOnID()) {

            if (Subsite::$force_subsite) $subsiteID = Subsite::$force_subsite;
            else {
                /* if($context = DataObject::context_obj()) $subsiteID = (int)$context->SubsiteID;
                  else */$subsiteID = (int) Subsite::currentSubsiteID();
            }

            $froms     = $query->getFrom();
            $froms     = array_keys($froms);
            $tableName = array_shift($froms);

            if ($subsiteID != 0) {
                $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
            }
        }
    }

    function onBeforeWrite()
    {
        if (!$this->owner->ID && !$this->owner->SubsiteID)
                $this->owner->SubsiteID = Subsite::currentSubsiteID();

        parent::onBeforeWrite();
    }

    static function accessible_sites_map($refresh = false)
    {
        if (!$refresh && self::$_accessible_sites_map_cache) {
            return self::$_accessible_sites_map_cache;
        }
        $subsites    = Subsite::accessible_sites("CMS_ACCESS_CMSMain");
        $subsitesMap = array();
        if ($subsites && $subsites->Count()) {
            $subsitesMap = $subsites->map('ID', 'Title');
        }
        self::$_accessible_sites_map_cache = $subsitesMap;
        return self::$_accessible_sites_map_cache;
    }

    static function accessible_sites_ids($refresh = false)
    {
        $map = self::accessible_sites_map($refresh);
        return array_keys($map);
    }

    static function check_accessible_sites_map($subsiteID, $refresh = false)
    {
        if (!$subsiteID) {
            return false;
        }
        $array = self::accessible_sites_map($refresh);
        return array_key_exists($subsiteID, $array);
    }

    function updateCMSFields(FieldList $fields)
    {
        $subsites    = Subsite::accessible_sites("CMS_ACCESS_CMSMain");
        $subsitesMap = array();
        if ($subsites && $subsites->Count()) {
            $subsitesMap = $subsites->map('ID', 'Title');
            unset($subsitesMap[$this->owner->SubsiteID]);
        }
        if (Subsite::currentSubsiteID()) {
            $fields->removeByName('SubsiteID');
        } else {
            $field = $fields->dataFieldByName('SubsiteID');
            if (!$field) {
                $fields->addFieldToTab('Root.Subsite',
                    new DropdownField('SubsiteID', 'Subsite', $subsitesMap));
            }
        }
    }

    function alternateSiteConfig()
    {
        if (!$this->owner->SubsiteID) return false;
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
    function canEdit($member = null)
    {
        if (!$member) $member = Member::currentUser();

        // Find the sites that this user has access to
        if ($member->ID == Member::currentUserID()) {
            $goodSites = self::accessible_sites_ids();
        } else {
            $goodSites = Subsite::accessible_sites('CMS_ACCESS_CMSMain', true,
                    'all', $member)->column('ID');
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

        // Return true if they have access to this object's site
        if (!(in_array(0, $goodSites) || in_array($subsiteID, $goodSites))) {
            return false;
        }
        return true;
    }

    /**
     * @return boolean
     */
    function canDelete($member = null)
    {
        if (!$member && $member !== FALSE) $member = Member::currentUser();

        return $this->canEdit($member);
    }

    /**
     * Called by ContentController::init();
     */
    static function contentcontrollerInit($controller)
    {
        $subsite = Subsite::currentSubsite();
        if ($subsite && $subsite->Theme)
                SSViewer::set_theme(Subsite::currentSubsite()->Theme);
    }

    function alternateAbsoluteLink()
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
    function cacheKeyComponent()
    {
        return 'subsite-'.Subsite::currentSubsiteID();
    }

    /**
     * @param Member
     * @return boolean|null
     */
    function canCreate($member = null)
    {
        // Typically called on a singleton, so we're not using the Subsite() relation
        $subsite = Subsite::currentSubsite();
        if ($subsite && $subsite->exists() && $subsite->PageTypeBlacklist) {
            $blacklisted = explode(',', $subsite->PageTypeBlacklist);
            // All subclasses need to be listed explicitly
            if (in_array($this->owner->class, $blacklisted)) return false;
        }
        return true;
    }
}