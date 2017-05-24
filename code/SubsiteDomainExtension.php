<?php

/**
 * 3.1 compat layer
 *
 * @author Kalyptus SPRL <thomas@kalyptus.be>
 */
class SubsiteDomainExtension extends DataExtension
{

    /**
     * Specifies that this subsite is http only
     */
    const PROTOCOL_HTTP = 'http';

    /**
     * Specifies that this subsite is https only
     */
    const PROTOCOL_HTTPS = 'https';

    /**
     * Specifies that this subsite supports both http and https
     */
    const PROTOCOL_AUTOMATIC = 'automatic';

    private static $db = array(
        "Protocol" => "Enum('http,https,automatic','automatic')",
    );

    public function updateCMSFields(\FieldList $fields)
    {
        $protocols = array(
            self::PROTOCOL_HTTP => _t('SubsiteDomain.PROTOCOL_HTTP', 'http://'),
            self::PROTOCOL_HTTPS => _t('SubsiteDomain.PROTOCOL_HTTPS', 'https://'),
            self::PROTOCOL_AUTOMATIC => _t('SubsiteDomain.PROTOCOL_AUTOMATIC', 'Automatic')
        );

        if (!$fields->dataFieldByName('Protocol')) {
            $Protocol = OptionsetField::create('Protocol', $this->owner->fieldLabel('Protocol'), $protocols)
                ->setDescription(_t(
                    'SubsiteDomain.PROTOCOL_DESCRIPTION', 'When generating links to this subsite, use the selected protocol. <br />' .
                    'Selecting \'Automatic\' means subsite links will default to the current protocol.'
            ));
            $fields->push($Protocol);
        }
    }

    /**
     * Get the link to this subsite
     *
     * @return string
     */
    public function Link()
    {
        return $this->owner->getFullProtocol() . $this->owner->Domain;
    }

    /**
     * Gets the full protocol (including ://) for this domain
     *
     * @return string
     */
    public function getFullProtocol()
    {
        switch ($this->owner->Protocol) {
            case self::PROTOCOL_HTTPS: {
                    return 'https://';
                }
            case self::PROTOCOL_HTTP: {
                    return 'http://';
                }
            default: {
                    return Director::protocol();
                }
        }
    }
}
