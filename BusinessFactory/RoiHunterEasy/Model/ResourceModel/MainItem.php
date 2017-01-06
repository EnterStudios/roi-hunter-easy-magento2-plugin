<?php
namespace BusinessFactory\RoiHunterEasy\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Stdlib\DateTime\DateTime;

// In Magento 2, the model class defines the methods an end-user-programmer will use to interact with a model’s data.
// A resource model class contains the methods that will actually fetch the information from the database.
// Each CRUD model in Magento 2 has a corresponding resource model class.


class MainItem extends AbstractDb
{
    private $datetime;

    public function __construct(Context $context, DateTime $dateTime)
    {
        $this->datetime = $dateTime;
        parent::__construct($context);
    }

    protected function _construct()
    {
        $this->_init('businessfactory_roihuntereasy_main', 'id');
    }

    protected function _beforeSave(AbstractModel $object)
    {
        // Automatically set creation and update row time.
        if ($object->isObjectNew() && !$object->getCreationTime()) {
            $object->setCreationTime($this->datetime->gmtDate());
        } else {
            $object->setUpdateTime($this->datetime->gmtDate());
        }
        return parent::_beforeSave($object);
    }
}
