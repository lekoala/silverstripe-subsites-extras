<?php

File::remove_extension('FileSubsites');
File::add_extension('SubsiteFileExtension');
Group::remove_extension('GroupSubsites');
Group::add_extension('SubsiteGroupExtension');