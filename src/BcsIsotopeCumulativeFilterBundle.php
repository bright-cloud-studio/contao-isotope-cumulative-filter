<?php

/**
 * @copyright  Bright Cliud Studio
 * @author     Bright Cloud Studio
 * @package    Contao Isotope Cumulative Filter
 * @license    LGPL-3.0+
 * @see	       https://github.com/bright-cloud-studio/contao-isotope-cumulative-filter
 */

namespace Bcs\IsotopeCumulativeFilterBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Bcs\IsotopeCumulativeFilterBundle\DependencyInjection\BcsIsotopeCumulativeFilterExtension;

class BcsIsotopeCumulativeFilterBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new BcsIsotopeCumulativeFilterExtension();
    }
}
