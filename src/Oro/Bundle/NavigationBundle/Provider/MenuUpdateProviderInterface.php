<?php

namespace Oro\Bundle\NavigationBundle\Provider;

use Oro\Bundle\NavigationBundle\Entity\MenuUpdateInterface;

interface MenuUpdateProviderInterface
{
    /**
     * Retrieve list of menu updates
     *
     * @param string $menu
     * @param int $ownershipType
     *
     * @return MenuUpdateInterface[]
     */
    public function getUpdates($menu, $ownershipType);
}
