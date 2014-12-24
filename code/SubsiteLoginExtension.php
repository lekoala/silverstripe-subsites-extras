<?php

/**
 * SubsiteLoginExtension
 *
 * @author lekoala
 */
class SubsiteLoginExtension extends DataExtension
{

    //this is shown before displaying security login page
    function onBeforeSecurityLogin()
    {
        if (isset($_GET['BackURL']) && strstr($_GET['BackURL'], '/admin/')) {

        }
    }
}