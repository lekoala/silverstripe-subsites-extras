<?php

File::remove_extension('FileSubsites');
File::add_extension('SubsiteFileExtension');

if(class_exists('Fluent')) {
    SiteTree::remove_extension('FluentSiteTree');
    SiteTree::add_extension('FluentSiteTreeSubsiteFix');
}