<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Xigen\ClearAttribute\Console\Command;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\Data\AttributeInterface as EavAttributeInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class NullAttribute extends Command
{
    const ATTRIBUTE_ARGUMENT = "attribute";

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var ProgressBarFactory
     */
    protected $progressBarFactory;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * console constructor
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Symfony\Component\Console\Helper\ProgressBarFactory $progressBarFactory
     * @param \Magento\Framework\App\ResourceConnection $resource
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        ProgressBarFactory $progressBarFactory,
        ResourceConnection $resource
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->progressBarFactory = $progressBarFactory;
        $this->connection = $resource->getConnection();
        $this->resource = $resource;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     * xigen:clearattribute:null <attribute>
     * bin/magento xigen:clearattribute:null special_price
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->state->setAreaCode(Area::AREA_GLOBAL);
        $this->input = $input;
        $this->output = $output;

        $attribute = $this->input->getArgument(self::ATTRIBUTE_ARGUMENT) ?: false;

        if ($attribute) {
            $helper = $this->getHelper('question');

            $question = new ConfirmationQuestion(
                (string) __(
                    'You are about to null product attribute data for %1. Are you sure? [y/N]',
                    $attribute
                ),
                false
            );

            if (!$helper->ask($this->input, $this->output, $question) && $this->input->isInteractive()) {
                return Cli::RETURN_FAILURE;
            }

            $this->output->writeln('[' . $this->dateTime->gmtDate() . '] Start');

            if ($eav = $this->getEavAttribute($attribute)) {
                if ($table = $this->resolveTable($eav[EavAttributeInterface::BACKEND_TYPE])) {
                    $attributeId = $eav[EavAttributeInterface::ATTRIBUTE_ID];
                    $this->output->writeln((string) __(
                        "[%1] <comment>Setting ID <error>%2</error> to NULL in <error>%3</error></comment>",
                        $this->dateTime->gmtDate(),
                        $attributeId,
                        $table
                    ));

                    $this->nullValue($attributeId, $table);
                    $this->output->writeln('[' . $this->dateTime->gmtDate() . '] Finish');

                    if ($this->input->isInteractive()) {
                        return Cli::RETURN_SUCCESS;
                    }
                }
            } else {
                $this->output->writeln((string) __(
                    "[%1] <error>Cannot find attribute code</error>",
                    $this->dateTime->gmtDate()
                ));
            }

            if ($this->input->isInteractive()) {
                return Cli::RETURN_FAILURE;
            }
        }
    }

    /**
     * Set attribute data null for attribute ID and table
     * @param null $attributeId
     * @param null $table
     * @return int
     */
    public function nullValue($attributeId = null, $table = null)
    {
        if (!$attributeId || !$table) {
            $this->output->writeln((string) __(
                "[%1] <error>Problem setting values to NULL</error>",
                $this->dateTime->gmtDate()
            ));
            return Cli::RETURN_FAILURE;
        }

        try {
            $this->connection->beginTransaction();
            $this->connection->update(
                $table,
                ['value' => new \Zend_Db_Expr('NULL')],
                [EavAttributeInterface::ATTRIBUTE_ID . ' = ?' => $attributeId]
            );
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $this->output->writeln((string) __(
                "[%1] <error>Problem setting values to NULL</error> : %2",
                $this->dateTime->gmtDate(),
                $e->getMessage()
            ));
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Resolve table
     * @param string $backendType
     * @return bool|string
     */
    public function resolveTable($backendType)
    {
        switch ($backendType) {
            case 'static':
            default:
                return false;
            case 'varchar':
                return $this->resource->getTableName('catalog_product_entity_varchar');
            case 'int':
                return $this->resource->getTableName('catalog_product_entity_int');
            case 'text':
                return $this->resource->getTableName('catalog_product_entity_text');
            case 'datetime':
                return $this->resource->getTableName('catalog_product_entity_datetime');
            case 'decimal':
                return $this->resource->getTableName('catalog_product_entity_decimal');
        }
    }

    /**
     * Fetch attribute data by code
     * @param null $attributeCode
     * @return array
     */
    public function getEavAttribute($attributeCode = null)
    {
        $select = $this->connection
            ->select()
            ->from($this->resource->getTableName('eav_attribute'))
            ->where(AttributeInterface::ATTRIBUTE_CODE . ' = ?', $attributeCode)
            ->where(ProductAttributeInterface::ENTITY_TYPE_ID . ' = ?', $this->getAttributeTypeId());

        return $this->connection->fetchRow($select);
    }

    /**
     * Get product attribute type ID from DB
     * @return int
     */
    public function getAttributeTypeId()
    {
        $select = $this->connection
            ->select()
            ->from($this->resource->getTableName('eav_entity_type'))
            ->where('entity_type_code = ?', ProductAttributeInterface::ENTITY_TYPE_CODE);
        return $this->connection->fetchOne($select);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("xigen:clearattribute:null");
        $this->setDescription("Null product attribute data");
        $this->setDefinition([
            new InputArgument(self::ATTRIBUTE_ARGUMENT, InputArgument::REQUIRED, "Name"),
        ]);
        parent::configure();
    }
}
