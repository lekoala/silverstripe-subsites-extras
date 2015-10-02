<?php

/**
 * A profile is a code based extension that changes aspects of the subsite
 * based on some conventions
 *
 * Your profiles must extend this class
 *
 * @author LeKoala <thomas@lekoala.be>
 */
class SubsiteProfile
{
    private static $_current_profile;

    public static function getProfileDir()
    {
        return strtolower(str_replace('Profile', '', self::$_current_profile));
    }

    public static function applyToFields($dataobject, FieldList $fields)
    {
        if (is_object($dataobject)) {
            $dataobject = get_class($dataobject);
        }
        $profile = self::$_current_profile;
        if (!$profile) {
            return;
        }
        $profile::enable_custom_fields($dataobject, $fields);
    }

    protected static function enable_custom_fields($class, FieldList $fields)
    {

    }

    protected static function change_field_title($fields, $fieldName, $title,
                                                 $tooltip = '')
    {
        $f = $fields->dataFieldByName($fieldName);
        if ($f) {
            $f->setTitle($title);
            if ($tooltip) {
                $f->setTooltip($tooltip);
            }
        }
    }

    protected static function enable_custom_translations()
    {
        $locale      = i18n::get_locale();
        $lang        = i18n::get_lang_from_locale($locale);
        $profileDir  = self::getProfileDir();
        $translators = array_reverse(i18n::get_translators(), true);

        // Make sure to include base translations
        i18n::include_by_locale($lang);

        foreach ($translators as $priority => $translators) {
            foreach ($translators as $name => $translator) {
                /* @var $adapter Zend_Translate_Adapter */
                $adapter = $translator->getAdapter();
                
                // Load translations from profile
                $filename = $adapter->getFilenameForLocale($lang);
                $filepath = Director::baseFolder()."/mysite/lang/".$profileDir.'/'.$filename;

                if ($filename && !file_exists($filepath)) continue;
                $adapter->addTranslation(
                    array('content' => $filepath, 'locale' => $locale)
                );
            }
        }
    }

    protected static function enable_custom_code()
    {

    }

    public static function enable()
    {
        if (self::$_current_profile) {
            return;
        }
        if (!class_exists('Subsite')) {
            return;
        }
        if (!Subsite::currentSubsiteID()) {
            return;
        }
        $profile = Subsite::currentSubsite()->Profile;
        if (!$profile) {
            return;
        }
        self::$_current_profile = $profile;

        $profile::enable_custom_translations();
        $profile::enable_custom_code();
    }
}