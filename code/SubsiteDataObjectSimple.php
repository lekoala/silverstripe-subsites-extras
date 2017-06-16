<?php

/**
 * A simple base extension for adding a has one relationship
 */
class SubsiteDataObjectSimple extends DataExtension
{

    private static $has_one = array(
        'Subsite' => 'Subsite',
    );

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

        // If you're querying by ID, don't filter
        if ($query->filtersOnID()) {
            return;
        }

        // Don't run on delete queries, since they are always tied to a specific ID.
        if ($query->getDelete()) {
            return;
        }

        // If we match on a subsite, don't filter twice
        $regexp = '/^(.*\.)?("|`)?SubsiteID("|`)?\s?=/';
        foreach ($query->getWhereParameterised($parameters) as $predicate) {
            if (preg_match($regexp, $predicate)) {
                return;
            }
        }

        $subsiteID = (int) Subsite::currentSubsiteID();

        $froms = $query->getFrom();
        $froms = array_keys($froms);
        $tableName = array_shift($froms);

        if ($subsiteID) {
            $query->addWhere("\"$tableName\".\"SubsiteID\" IN ($subsiteID)");
        }
    }

    public function onBeforeWrite()
    {
        if ((!is_numeric($this->owner->ID) || !$this->owner->ID) && !$this->owner->SubsiteID) {
            $this->owner->SubsiteID = Subsite::currentSubsiteID();
        }
    }

    /**
     * Return a piece of text to keep DataObject cache keys appropriately specific
     */
    public function cacheKeyComponent()
    {
        return 'subsite-' . Subsite::currentSubsiteID();
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->push(new HiddenField('SubsiteID', 'SubsiteID', Subsite::currentSubsiteID()));
    }
}
