<?php

/**
 * FluentSiteTreeSubsiteFix
 *
 * A simple fix while this pull requests wait to be merged
 * https://github.com/tractorcow/silverstripe-fluent/pull/108
 *
 * @author lekoala
 */
class FluentSiteTreeSubsiteFix extends DataExtension
{

    public function updateCMSFields(FieldList $fields)
    {
        // Fix URLSegment field issue for root pages
        if (!SiteTree::config()->nested_urls || empty($this->owner->ParentID)) {
            $baseUrl = Director::baseURL();
            if (class_exists('Subsite') && $this->owner->SubsiteID) {
                $baseUrl = Director::protocol().$this->owner->Subsite()->domain().'/';
            }
            $baseLink = Director::absoluteURL(Controller::join_links(
                        $baseUrl, Fluent::alias(Fluent::current_locale()), '/'
            ));

            /* @var SiteTreeURLSegmentField $originalUrlSegment */
            $originalUrlSegment = $fields->dataFieldByName("URLSegment");

            $urlsegment = new SiteTreeURLSegmentFieldFixed("URLSegment",
                $originalUrlSegment->Title());
            $urlsegment->setHelpText($originalUrlSegment->getHelpText());

            $fields->replaceField('URLSegment', $urlsegment);
            $urlsegment->setURLPrefix($baseLink);
            $urlsegment->setCannotSetPrefix(true);
        }
    }
}

/**
 * Prevent any other modifications by Fluent
 */
class SiteTreeURLSegmentFieldFixed extends SiteTreeURLSegmentField
{
    protected $cannot_set_prefix = false;

    public function setCannotSetPrefix($v)
    {
        $this->cannot_set_prefix = $v;
    }

    public function setURLPrefix($url)
    {
        if ($this->cannot_set_prefix) {
            return;
        }
        parent::setURLPrefix($url);
    }
}