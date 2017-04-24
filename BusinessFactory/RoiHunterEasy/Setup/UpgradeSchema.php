<?php

namespace BusinessFactory\RoiHunterEasy\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        //handle all possible upgrade versions
        if (!$context->getVersion()) {
            // No previous version found, installation, InstallSchema was just executed
        } else {
            if (version_compare($context->getVersion(), '1.1.0') < 0) {
                // Get module table
                $tableName = $setup->getTable('businessfactory_roihuntereasy_main');

                // Check if the table already exists
                if ($setup->getConnection()->isTableExists($tableName) == true) {
                    // Declare data
                    $columns = [
                        'conversion_label' => [
                            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                            'size' => 255,
                            'nullable' => true,
                            'comment' => 'Conversion label'
                        ],
                    ];

                    $connection = $setup->getConnection();
                    foreach ($columns as $name => $definition) {
                        $connection->addColumn($tableName, $name, $definition);
                    }
                } else {
//                    \Monolog\Handler\error_log("ROI Hunter Easy table didn't exists.");
                }
            }
        }

        $setup->endSetup();
    }
}