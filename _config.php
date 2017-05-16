<?php

// The recommended way by subsite-extras is to use one folder for each subsite. All other folders are globals
// Assets should not be managed by subsite admins
AssetAdmin::remove_extension('SubsiteMenuExtension');
File::remove_extension('FileSubsites');
File::add_extension('SubsiteFileExtension');
FileSubsites::$default_root_folders_global = true;
SubsiteFileExtension::$default_root_folders_global = true;
