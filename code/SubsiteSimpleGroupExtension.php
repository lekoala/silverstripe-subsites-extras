<?php

/**
 * Description of SubsiteSimpleGroupExtension
 *
 * @author Koala
 */
class SubsiteSimpleGroupExtension extends DataExtension
{
    private static $db = array(
        'CanSelectSubsite' => 'Boolean'
    );
    private static $defaults = array(
        'CanSelectSubsite' => true
    );

    public function updateCMSFields(\FieldList $fields)
    {
        $fields->addFieldToTab('Root.Subsites', new CheckboxField('CanSelectSubsite', _t('SubsitesExtra.CanSelectSubsite', 'Can select subsite in admin')));
    }
}
