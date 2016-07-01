<?php

/**
 * Only show if no subsite is active.
 * Maybe better to handle this through proper permissions in the cms
 */
class SubsiteActiveMenuExtension extends Extension
{

    public function subsiteCMSShowInMenu()
    {
        if (Subsite::currentSubsite()) {
            return false;
        }
        return true;
    }
}