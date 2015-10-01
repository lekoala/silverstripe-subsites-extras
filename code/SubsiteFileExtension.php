<?php

/**
 * An alternative to the official SubsiteFileExtension
 *
 * @author lekoala
 */
class SubsiteFileExtension extends DataExtension
{
    /**
     * Set this to true to make all folders belong to main site by default
     * @var bool
     */
    static $default_root_folders_global = false;
    private static $db                  = array(
        'ShowInSubsites' => 'Boolean',
    );
    private static $has_one             = array(
        'Subsite' => 'Subsite',
    );
    private static $defaults            = array(
        'ShowInSubsites' => false
    );

    /**
     * Amends the CMS tree title for folders in the Files & Images section.
     * Prefixes a '* ' to the folders that are accessible from all subsites.
     */
    function alternateTreeTitle()
    {
        if ($this->owner->SubsiteID == 0 && $this->owner->ShowInSubsites)
                return " * ".$this->owner->Title;
        else return $this->owner->Title;
    }

    /**
     * Add subsites-specific fields to the folder editor.
     */
    function updateCMSFields(FieldList $fields)
    {
        $ctrl = null;
        if (Controller::has_curr()) {
            $ctrl = Controller::curr();
        }

        if (!$ctrl) {
            return;
        }

        // This fixes fields showing up for no reason in the list view (not moved to Details tab)
        if ($ctrl->getAction() !== 'EditForm') {
            return;
        }

        if ($this->owner instanceof Folder) {
            // Allow to move folders from one site to another
            $sites     = Subsite::accessible_sites('CMS_ACCESS_AssetAdmin');
            $values    = array();
            $values[0] = _t('FileSubsites.AllSitesDropdownOpt', 'All sites');
            foreach ($sites as $site) {
                $values[$site->ID] = $site->Title;
            }
            ksort($values);
            if ($sites) {
                //Dropdown needed to move folders between subsites
                $dropdown = new DropdownField(
                    'SubsiteID',
                    _t('FileSubsites.SubsiteFieldLabel', 'Subsite'), $values
                );
                $dropdown->addExtraClass('subsites-move-dropdown');
                $fields->push($dropdown);
            }

            // On main site, allow showing this folder in subsite
            if ($this->owner->SubsiteID == 0 && !Subsite::currentSubsiteID()) {
                $fields->push(new CheckboxField('ShowInSubsites',
                    _t('SubsiteFileExtension.ShowInSubsites', 'Show in subsites')));
            }
        }
    }

    /**
     * Update any requests to limit the results to the current site
     */
    function augmentSQL(SQLQuery &$query)
    {
        if (Subsite::$disable_subsite_filter) return;

        // If you're querying by ID, ignore the sub-site - this is a bit ugly... (but it was WAYYYYYYYYY worse)
        //@TODO I don't think excluding if SiteTree_ImageTracking is a good idea however because of the SS 3.0 api and ManyManyList::removeAll() changing the from table after this function is called there isn't much of a choice

        $from  = $query->getFrom();
        $where = $query->getWhere();

        if (!isset($from['SiteTree_ImageTracking']) && !($where && preg_match('/\.(\'|"|`|)ID(\'|"|`|)/',
                $where[0]))) {
            $subsiteID = (int) Subsite::currentSubsiteID();

            // The foreach is an ugly way of getting the first key :-)
            foreach ($query->getFrom() as $tableName => $info) {
                $where = "\"$tableName\".\"SubsiteID\" IN (0, $subsiteID)";
                $query->addWhere($where);
                break;
            }

            $sect       = array_values($query->getSelect());
            $isCounting = strpos($sect[0], 'COUNT') !== false;

            // Ordering when deleting or counting doesn't apply
            if (!$query->getDelete() && !$isCounting) {
                $query->addOrderBy("\"SubsiteID\"");
            }
        }
    }

    function canView()
    {
        // This is required, otherwise the whole list is not displayed
        if (Controller::curr() instanceof LeftAndMain) {
            if ($this->owner->ID && Subsite::currentSubsiteID()) {
                if ($this->owner->SubsiteID == 0 && !$this->owner->ShowInSubsites) {
                    return false;
                }
            }
        }
    }

    function onBeforeWrite()
    {
        if (!$this->owner->ID && !$this->owner->SubsiteID) {
            if (self::$default_root_folders_global) {
                $this->owner->SubsiteID = 0;
            } else {
                $this->owner->SubsiteID = Subsite::currentSubsiteID();
            }
        }
    }

    function onAfterUpload()
    {
        $parent = $this->owner->Parent();

        $this->owner->SubsiteID = Subsite::currentSubsiteID();

        $this->owner->write();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Cascade this
        $parent = $this->owner->Parent();
        $show   = $this->owner->SubsiteID && ($this->owner->SubsiteID != $parent->SubsiteID);
        if ($show && !$parent->ShowInSubsites) {
            $parent->ShowInSubsites = true;
            $parent->write();
        }
    }

    function canEdit($member = null)
    {
        // Check the CMS_ACCESS_SecurityAdmin privileges on the subsite that owns this group
        $subsiteID = Session::get('SubsiteID');
        if ($subsiteID && $subsiteID == $this->owner->SubsiteID) {
            return true;
        } else {
            Session::set('SubsiteID', $this->owner->SubsiteID);
            $access = Permission::check('CMS_ACCESS_CMSMain');
            Session::set('SubsiteID', $subsiteID);

            return $access;
        }
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     */
    function cacheKeyComponent()
    {
        return 'subsite-'.Subsite::currentSubsiteID();
    }
}