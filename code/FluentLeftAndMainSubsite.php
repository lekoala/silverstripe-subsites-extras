<?php

/**
 * An extension that takes into account that some locales can be disabled
 * on a subsite if the ActiveLocalesExtension is used
 */
class FluentLeftAndMainSubsite extends LeftAndMainExtension {
	public function init() {
        $conf = SiteConfig::current_site_config();

        $localesNames = Fluent::locale_names();
        if($conf->hasExtension('ActiveLocalesExtension') && $conf->ActiveLocales) {
            $localesNames = $conf->ActiveLocalesNames();
        }
		$locales = json_encode($localesNames);
		$locale = json_encode(Fluent::current_locale());
        // If we have only one locale, set this one as default
        if(count($localesNames) === 1) {
            $locale = json_encode(key($localesNames));
        }
		$param = json_encode(Fluent::config()->query_param);
		$buttonTitle = json_encode(_t('Fluent.ChangeLocale', 'Change Locale'));

        Requirements::block('FluentHeadScript');
		Requirements::insertHeadTags(<<<EOT
<script type="text/javascript">
//<![CDATA[
	var fluentLocales = $locales;
	var fluentLocale = $locale;
	var fluentParam = $param;
	var fluentButtonTitle = $buttonTitle;
//]]>
</script>
EOT
			,'FluentHeadScriptSubsite'
		);
	}
}
