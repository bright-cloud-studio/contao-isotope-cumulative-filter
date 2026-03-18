<?php

// Register the SeoAwareCumulativeFilter as an Isotope frontend module
$GLOBALS['ISO_MOD']['filter']['BcsCumulativeFilter'] = [
    'extends' => 'cumulativefilter',
];

$GLOBALS['FE_MOD']['isotope']['BcsCumulativeFilter'] =
    'Bcs\IsotopeCumulativeFilterBundle\Module\BcsCumulativeFilter';
