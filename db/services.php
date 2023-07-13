<?php

defined('MOODLE_INTERNAL') || die();

$services = array(

        // Define service for NEW GU SPDETAILS
        'New GU Details' => array(
            'functions' => ['block_staff_dashboard_get_groupusers', 'block_newgu_spdetails_get_coursegroups', 'block_newgu_spdetails_get_statistics'],
            'requiredcapability' => '',
            'restrictedusers' => 1,
            'enabled' => 1,
        ),
);

$functions = array(

    'block_newgu_spdetails_get_groupusers' => array(
        'classpath' => 'block/newgu_spdetails/classes/external.php',
        'classname'   => 'block_newgu_spdetails_external',
        'methodname'  => 'get_groupusers',
        'description' => 'Get group users',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),

    'block_newgu_spdetails_get_coursegroups' => array(
        'classpath' => 'block/newgu_spdetails/classes/external.php',
        'classname'   => 'block_newgu_spdetails_external',
        'methodname'  => 'get_coursegroups',
        'description' => 'Get course groups',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),

    'block_newgu_spdetails_get_statistics' => array(
        'classpath' => 'block/newgu_spdetails/classes/external.php',
        'classname'   => 'block_newgu_spdetails_external',
        'methodname'  => 'get_statistics',
        'description' => 'Get users course statistics',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),

);
