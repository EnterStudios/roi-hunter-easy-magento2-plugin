<?php
namespace BusinessFactory\RoiHunterEasy\Setup;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        $table = $installer->getConnection()->newTable($installer->getTable('businessfactory_roihuntereasy_main')
        )->addColumn(
            'id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'ID'
        )->addColumn(
            'description',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false],
            'Description'
        )->addColumn(
            'google_analytics_ua',
            Table::TYPE_TEXT,
            255,
            ['nullable' => true],
            'Google Analytics UA'
        )->addColumn(
            'customer_id',
            Table::TYPE_TEXT,
            255,
            ['nullable' => true],
            'Customer Id'
        )->addColumn(
            'access_token',
            Table::TYPE_TEXT,
            255,
            ['nullable' => true],
            'Access Token'
        )->addColumn(
            'client_token',
            Table::TYPE_TEXT,
            255,
            ['nullable' => true],
            'Client Token'
        )->addColumn(
            'conversion_id',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => true, 'unsigned' => true],
            'Conversion id'
        )->addColumn(
            'managed_merchants',
            Table::TYPE_BOOLEAN,
            null,
            ['nullable' => true],
            'Managed merchants by us'
        )->addColumn(
            'adult_oriented',
            Table::TYPE_BOOLEAN,
            null,
            ['nullable' => true],
            'Adult oriented'
        )->addColumn(
            'status',
            Table::TYPE_TEXT,
            255,
            ['nullable' => true],
            'Goostav status'
        )->addColumn(
            'errors',
            Table::TYPE_TEXT,
            511,
            ['nullable' => true],
            'Errors'
        )->addColumn(
            'creation_state',
            Table::TYPE_TEXT,
            255,
            ['nullable' => true],
            'Creation State'
        )->addColumn(
            'creation_time',
            Table::TYPE_TIMESTAMP,
            null,
            [],
            'Creation Time'
        )->addColumn(
            'update_time',
            Table::TYPE_TIMESTAMP,
            null,
            [],
            'Modification Time'
        )->addColumn(
            'is_active',
            Table::TYPE_SMALLINT,
            null,
            ['nullable' => false, 'default' => '1'],
            'Is Active'
        )->addColumn(
            'conversion_label',
            Table::TYPE_TEXT,
            255,
            ['nullable' => true],
            'Conversion label'

        )->setComment(
            'Data for ROI Hunter Easy module'
        );
        $installer->getConnection()->createTable($table);
        $installer->endSetup();
    }
}
