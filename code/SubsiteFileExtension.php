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
    public static $default_root_folders_global = false;
    public static $ignore_file_filter = false;
    private static $has_one = array(
        'Subsite' => 'Subsite',
    );

    /**
     * Amends the CMS tree title for folders in the Files & Images section.
     * Prefixes a '* ' to the folders that are accessible from all subsites.
     */
    public function alternateTreeTitle()
    {
        if ($this->owner->SubsiteID == 0 && $this->owner instanceof Folder) {
            return " * " . $this->owner->Title;
        } else {
            return $this->owner->Title;
        }
    }

    /**
     * Update any requests to limit the results to the current site
     */
    public function augmentSQL(SQLQuery &$query, DataQuery &$dataQuery = null)
    {
        if (Subsite::$disable_subsite_filter) {
            return;
        }
        if ($dataQuery && $dataQuery->getQueryParam('Subsite.filter') === false) {
            return;
        }
        if (self::$ignore_file_filter) {
            return;
        }

        $from = $query->getFrom();

        if (isset($from['SiteTree_ImageTracking']) || $query->filtersOnID()) {
            return;
        }

        $subsiteID = (int) Subsite::currentSubsiteID();

        // The foreach is an ugly way of getting the first key :-)
        foreach ($query->getFrom() as $tableName => $info) {
            $where = "\"$tableName\".\"SubsiteID\" IN (0, $subsiteID)";
            $query->addWhere($where);
            break;
        }

        $sect = array_values($query->getSelect());
        $isCounting = strpos($sect[0], 'COUNT') !== false;

        // Ordering when deleting or counting doesn't apply
        if (!$query->getDelete() && !$isCounting) {
            $query->addOrderBy("\"SubsiteID\"");
        }
    }

    public function onBeforeWrite()
    {
        if ($this->owner->Parent()) {
            $this->owner->SubsiteID = $this->owner->Parent()->SubsiteID;
        } else {
            if (self::$default_root_folders_global) {
                $this->owner->SubsiteID = 0;
            } else {
                $this->owner->SubsiteID = Subsite::currentSubsiteID();
            }
        }
    }

    public function onAfterUpload()
    {
        // If we have a parent, use it's subsite as our subsite
        if ($this->owner->Parent()) {
            $this->owner->SubsiteID = $this->owner->Parent()->SubsiteID;
        } else {
            $this->owner->SubsiteID = Subsite::currentSubsiteID();
        }
        $this->owner->write();
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
    }

    public function canCreate($member = null)
    {
        return true;
    }

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return true;

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
    public function cacheKeyComponent()
    {
        return 'subsite-' . Subsite::currentSubsiteID();
    }
}
