<?php
namespace BusinessFactory\RoiHunterEasy\Model;

// In Magento 2, the model class defines the methods an end-user-programmer will use to interact with a model’s data.
// A resource model class contains the methods that will actually fetch the information from the database.
// Each CRUD model in Magento 2 has a corresponding resource model class.

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

class MainItem extends AbstractModel implements MainItemInterface, IdentityInterface
{
    const CACHE_TAG = 'businessfactory_roihuntereasy_main';

    protected function _construct()
    {
        $this->_init('BusinessFactory\RoiHunterEasy\Model\ResourceModel\MainItem');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
