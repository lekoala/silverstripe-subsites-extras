<?php

/**
 * Extension for SubsiteAdmin
 *
 * @author lekoala
 */
class SubsiteAdminBackLinkExtension extends Extension
{

    /**
     * Make "save and close" from betterbuttons work properly
     * @return string
     */
    public function BackLink()
    {
        return '/admin/subsites';
    }
}