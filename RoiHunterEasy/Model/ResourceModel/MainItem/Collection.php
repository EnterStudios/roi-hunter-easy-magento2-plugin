<?php
namespace BusinessFactory\RoiHunterEasy\Model\ResourceModel\MainItem;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('BusinessFactory\RoiHunterEasy\Model\MainItem',
            'BusinessFactory\RoiHunterEasy\Model\ResourceModel\MainItem');
    }
}
