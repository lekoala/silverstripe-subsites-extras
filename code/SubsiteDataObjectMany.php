<?php

/**
 * Extension for a dataobject belonging to multiple subsites
 *
 * Don't forget to add $belongs_many_many on the subsite as well through an extension
 */
class SubsiteDataObjectMany extends DataExtension
{
    private static $_accessible_sites_map_cache = null;
    private static $db                          = array(
        'SubsiteList' => 'Varchar(255)' //use as cache to avoid adding lots of overhead
    );
    private static $many_many                   = array(
        'Subsites' => 'Subsite',
    );

    public static function add_to_class($class, $extensionClass, $args = null)
    {

    }

    function isMainDataObject()
    {
        if ($this->owner->SubsiteList == '') return true;
        return false;
    }

    function listSubsiteIDs()
    {
        if ($this->owner->SubsiteList == '') {
            return array();
        }
        $list = explode(',', $this->owner->SubsiteList);
        $ids  = array();
        foreach ($list as $l) {
            if ($l == '') {
                continue;
            }
            $ids[] = trim($l, '#');
        }
        return $ids;
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
        $ctrl = null;
        if (Controller::has_curr()) {
            $ctrl = Controller::curr();
        }

        if (Subsite::$disable_subsite_filter) return;
        if ($dataQuery->getQueryParam('Subsite.filter') === false) return;
        if ($ctrl && get_class($ctrl) == 'Security') return;

        // Don't run on delete queries, since they are always tied to
        // a specific ID.
        if ($query->getDelete()) return;

        // If you're querying by ID, ignore the sub-site - this is a bit ugly...
        if (!$query->filtersOnID()) {

            if (Subsite::$force_subsite) $subsiteID = Subsite::$force_subsite;
            else {
                $subsiteID = (int) Subsite::currentSubsiteID();
            }

            $froms     = $query->getFrom();
            $froms     = array_keys($froms);
            $tableName = array_shift($froms);

            if ($subsiteID != 0) {
                $query->addWhere("\"$tableName\".\"SubsiteList\" LIKE '%#$subsiteID,%'");
            }
        }
    }

    function buildSubsiteList()
    {
        $list = '';
        foreach ($this->owner->Subsites() as $sub) {
            if (!is_object($sub)) {
                continue;
            }
            $list .= '#'.$sub->ID.',';
        }
        $this->owner->SubsiteList = $list;

        return $list;
    }

    function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->owner->ID && Subsite::currentSubsiteID()) {
            $this->owner->Subsites()->add(Subsite::currentSubsiteID());
        }
        $this->buildSubsiteList();
    }

    function onAfterWrite()
    {
        parent::onAfterWrite();
    }

    function updateCMSFields(FieldList $fields)
    {
        if (!$this->owner->ID) {
            return;
        }

        $accessibleSubsites    = Subsite::accessible_sites("CMS_ACCESS_CMSMain");
        $accessibleSubsitesMap = array();
        if ($accessibleSubsites && $accessibleSubsites->Count()) {
            $accessibleSubsitesMap = $accessibleSubsites->map('ID', 'Title');
            unset($accessibleSubsitesMap[$this->owner->SubsiteID]);
        }
        $fields->removeByName('SubsiteList');
        if (Subsite::currentSubsiteID()) {
            $fields->removeByName('Subsites');
        } else {
            $currentSubsites = $this->owner->Subsites();

            $fields->removeByName('Subsites');
            $conf = new GridFieldConfig_RecordViewer();
            if (!Permission::check('ADMIN')) {
                $conf->removeComponentsByType('GridFieldAddNewButton');
            }
            $grid = new GridField('Subsites', 'Subsites', $currentSubsites,
                $conf);
            $fields->addFieldToTab('Root.Subsites', $grid);
            $fields->addFieldToTab('Root.Subsites',
                new ReadonlyField('SubsiteList'));
        }

        // Profile integration
        SubsiteProfile::applyToFields($this->owner, $fields);
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
        // If no subsite ID is defined, let dataobject determine the permission
        if (!$this->owner->SubsiteList || !Subsite::currentSubsiteID()) {
            return null;
        }

        if ($this->owner->SubsiteList) {
            $subsiteIDs = $this->listSubsiteIDs();
        } else {
            // The relationships might not be available during the record creation when using a GridField.
            // In this case the related objects will have empty fields, and SubsiteID will not be available.
            //
			// We do the second best: fetch the likely SubsiteID from the session. The drawback is this might
            // make it possible to force relations to point to other (forbidden) subsites.
            $subsiteIDs = array(Subsite::currentSubsiteID());
        }

        if (!$member) $member = Member::currentUser();

        // Find the sites that this user has access to
        if ($member->ID == Member::currentUserID()) {
            $goodSites = SubsiteDataObject::accessible_sites_ids();
        } else {
            $goodSites = Subsite::accessible_sites('CMS_ACCESS_CMSMain', true, 'all',
                    $member)->column('ID');
        }


        // Return true if they have access to this object's site
        if (in_array(0, $goodSites)) {
            return true; //if you can edit main site, you can edit subsite
        }
        foreach ($subsiteIDs as $id) {
            if (in_array($id, $goodSites)) {
                return true;
            }
        }
        return false;
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
     * Gets all classes with this extension
     *
     * @return array of classes to migrate
     */
    public static function extendedClasses()
    {
        $classes     = array();
        $dataClasses = ClassInfo::subclassesFor('DataObject');
        array_shift($dataClasses);
        foreach ($dataClasses as $class) {
            $base = ClassInfo::baseDataClass($class);
            foreach (Object::get_extensions($base) as $extension) {
                if (is_a($extension, __CLASS__, true)) {
                    $classes[] = $base;
                    break;
                }
            }
        }
        return array_unique($classes);
    }

    function alternateAbsoluteLink()
    {
        // Generate the existing absolute URL and replace the domain with the subsite domain.
        // This helps deal with Link() returning an absolute URL.
        $url = Director::absoluteURL($this->owner->Link());
        if (Subsite::currentSubsiteID()) {
            $url = preg_replace('/\/\/[^\/]+\//',
                '//'.Subsite::currentSubsite()->domain().'/', $url);
        }
        return $url;
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     */
    function cacheKeyComponent()
    {
        return 'subsite-'.str_replace(',', '-', $this->owner->SubsiteList);
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