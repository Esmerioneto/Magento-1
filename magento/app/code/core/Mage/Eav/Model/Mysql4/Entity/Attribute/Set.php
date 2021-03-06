<?php
/**
 * Magento Enterprise Edition
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Magento Enterprise Edition License
 * that is bundled with this package in the file LICENSE_EE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.magentocommerce.com/license/enterprise-edition
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_Eav
 * @copyright   Copyright (c) 2010 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://www.magentocommerce.com/license/enterprise-edition
 */


/**
 * Eav attribute set resource model
 *
 * @category   Mage
 * @package    Mage_Eav
 * @author     Magento Core Team <core@magentocommerce.com>
 */
class Mage_Eav_Model_Mysql4_Entity_Attribute_Set extends Mage_Core_Model_Mysql4_Abstract
{
    /**
     * Initialize connection
     *
     */
    protected function _construct()
    {
        $this->_init('eav/attribute_set', 'attribute_set_id');
    }

    /**
     * Perform actions after object save
     *
     * @param Mage_Core_Model_Abstract $object
     * @return Mage_Eav_Model_Mysql4_Entity_Attribute_Set
     */
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        if ($object->getGroups()) {
            /* @var $group Mage_Eav_Model_Entity_Attribute_Group */
            foreach ($object->getGroups() as $group) {
                $group->setAttributeSetId($object->getId());
                if ($group->itemExists() && !$group->getId()) {
                    continue;
                }
                $group->save();
            }
        }
        if ($object->getRemoveGroups()) {
            foreach ($object->getRemoveGroups() as $group) {
                /* @var $group Mage_Eav_Model_Entity_Attribute_Group */
                $group->delete();
            }
            Mage::getResourceModel('eav/entity_attribute_group')->updateDefaultGroup($object->getId());
        }
        if ($object->getRemoveAttributes()) {
            foreach ($object->getRemoveAttributes() as $attribute) {
                /* @var $attribute Mage_Eav_Model_Entity_Attribute */
                $attribute->deleteEntity();
            }
        }
        return parent::_afterSave($object);
    }

    /**
     * Validate attribute set name
     *
     * @param Mage_Eav_Model_Entity_Attribute_Set $object
     * @param string $name
     * @return bool
     */
    public function validate($object,$name)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()->from($this->getMainTable())
            ->where("attribute_set_name=?",$name)
            ->where("entity_type_id=?",$object->getEntityTypeId());

        if ($object->getId()) {
            $select->where("attribute_set_id!=?",$object->getId());
        }

        if (!$read->fetchOne($select)) {
           return true;
        }

        return false;
    }

    /**
     * Retrieve Set info by attributes
     *
     * @param array $attributeIds
     * @param int $setId
     * @return array
     */
    public function getSetInfo(array $attributeIds, $setId = null)
    {
        $setInfo            = array();
        $attributeToSetInfo = array();
        if (count($attributeIds) > 0) {
            $select = $this->_getReadAdapter()->select()
                ->from(
                    array('entity' => $this->getTable('entity_attribute')),
                    array('attribute_id','attribute_set_id', 'attribute_group_id', 'sort_order'))
                ->joinLeft(
                    array('group' => $this->getTable('attribute_group')),
                    'entity.attribute_group_id=group.attribute_group_id',
                    array('group_sort_order' => 'sort_order'))
                ->where('entity.attribute_id IN (?)', $attributeIds);
            if (is_numeric($setId)) {
                $select->where('entity.attribute_set_id=?', $setId);
            }
            $result = $this->_getReadAdapter()->fetchAll($select);

            foreach ($result as $row) {
                $data = array(
                    'group_id'      => $row['attribute_group_id'],
                    'group_sort'    => $row['group_sort_order'],
                    'sort'          => $row['sort_order']
                );
                $attributeToSetInfo[$row['attribute_id']][$row['attribute_set_id']] = $data;
            }
        }

        foreach ($attributeIds as $atttibuteId) {
            $setInfo[$atttibuteId] = isset($attributeToSetInfo[$atttibuteId])
                ? $attributeToSetInfo[$atttibuteId]
                : array();
        }

        return $setInfo;
    }

    /**
     * Retrurn default attribute group id for attribute set id
     *
     * @param int $setId
     * @return int|null
     */
    public function getDefaultGroupId($setId)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable('eav/attribute_group'), 'attribute_group_id')
            ->where('attribute_set_id = ?', $setId)
            ->where('default_id = ?', 1)
            ->limit(1);
        return $this->_getReadAdapter()->fetchOne($select);
    }
}
