<?php

/**
 * SubsiteAssetAdminExtension
 *
 * @author lekoala
 */
class SubsiteAssetAdminExtension extends LeftAndMainExtension
{

    function updateEditForm(Form $form)
    {
        $gridField = $form->Fields()->dataFieldByName('File');
        $columns   = $gridField->getConfig()->getComponentByType('GridFieldDataColumns');
        $columns->setDisplayFields(array(
            'StripThumbnail' => '',
            'alternateTreeTitle' => _t('File.Name'),
            'Created' => _t('AssetAdmin.CREATED', 'Date'),
            'Size' => _t('AssetAdmin.SIZE', 'Size'),
        ));
    }
}