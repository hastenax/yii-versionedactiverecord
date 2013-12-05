<?php

class VersionedActiveRecord extends CActiveRecord
{
    //column value can be null
    protected $_keyColumn = 'ParentID';
    
    //subparent, for multiple Versioned Models for one ParentID
    //column value can be null
    protected $_subKeyColumn = '';
    
    // automatically set to current date on create/update
    protected $_versionDateColumn = 'VersionDate';
    
    // automatically set to 0 for deleted, old updated rows
    // must have default value 1 in database
    protected $_actualityColumn = 'IsActual';
    
    // automatically set to current date on delete
    protected $_deletedDateColumn = 'DeletedDate';
    
    private $_oldAttributes;
    
    public function defaultScope()
    {
        $isActualColumn = $this->_actualityColumn;    
        return array(
            'condition'=>$this->getTableAlias(false, false).'.'.$isActualColumn.'=1',
        );
    }
    
    protected function deleteInternal($model = null)
    {
        if (is_null($model))
            $model = $this;

        $deletedDateColumn = $model->_deletedDateColumn;
        $isActualColumn = $this->_actualityColumn;    
        $model->$isActualColumn = 0;
        $model->$deletedDateColumn = date('d.m.Y H:i:s');    
    }
    
    protected function getDeleteCriteria()
    {
        $keyColumn = $this->_keyColumn;
        $subKeyColumn = $this->_subKeyColumn;
        $isActualColumn = $this->_actualityColumn;

        $criteria = new CDbCriteria();
        if (strlen(trim($this->$keyColumn)))
            $criteria->addCondition($keyColumn.'='.$this->$keyColumn);
        $criteria->addCondition($isActualColumn.'=1');

        if (trim($subKeyColumn)) {
            $subKeyColumnValue = isset($this->_oldAttributes[$subKeyColumn]) ? 
            $this->_oldAttributes[$subKeyColumn] :
            $this->$subKeyColumn;
            if (strlen(trim($subKeyColumnValue)))
                $criteria->compare($subKeyColumn,$subKeyColumnValue);
            else
                $criteria->addCondition('('.$subKeyColumn.' IS NULL OR '.$subKeyColumn.' = \'\')');
        }

        return $criteria;
    }
    
    public function delete() {
        $isActualColumn = $this->_actualityColumn;
        $deletedDateColumn = $this->_deletedDateColumn;

        //see if some deleted
        $return = $this->model()->updateAll(
            array(
                $isActualColumn => 0,
                $this->_deletedDateColumn => date('d.m.Y H:i:s'),
            ),
            $this->getDeleteCriteria()
        );

        $this->deleteInternal();

        //return results
        return true;
    }
    
    public function deleteByPk($pk,$condition='',$params=array())
    {
        $model = $this->model()->findByPk($pk, $condition, $params);
        if (!is_null($model))
            return $model->delete() ? 1 : 0;
    }
    
    public function deleteAll($condition = '', $params = array()) {
        $models = $this->model()->findAll($condition, $params);
        $c = 0;
        foreach($models as $model)
            $c += $model->delete() ? 1 : 0;
        return $c;
    }        
    
    public function deleteAllByAttributes($attributes, $condition = '', $params = array()) {
        $models = $this->model()->findAllByAttributes($attributes, $condition, $params);
        $c = 0;
        foreach($models as $model)
            $c += $model->delete() ? 1 : 0;
        return $c;
    }     
    
    protected function afterFind()
    {
        parent::afterFind();
        $this->_oldAttributes = $this->attributes;
        unset($this->_oldAttributes[$this->_versionDateColumn]);
        unset($this->_oldAttributes[$this->_deletedDateColumn]);
        unset($this->_oldAttributes[$this->tableSchema->primaryKey]);
    }
    
    public function isChangedChildren()
    {
        return FALSE;
    }
    
    public function isChanged()
    {
        $result = count(array_diff_assoc($this->_oldAttributes, $this->attributes)) > 0;
        return $result || $this->isChangedChildren();
    }
    
    public function isDeleting()
    {
        return ($this->_oldAttributes[$this->_actualityColumn] == 1 && $this->attributes[$this->_actualityColumn] == 0);
    }

    public function save($runValidation=true,$attributes=null)
    {
        $versionDateColumn = $this->_versionDateColumn;
        $isActualColumn = $this->_actualityColumn;
        $keyColumn = $this->_keyColumn;

        if (!$this->isDeleting()) {
            if(!$this->isNewRecord && is_array($this->_oldAttributes)) {
                //check if something changed
                if (!$this->isChanged()) {
                    return $this->validate();//true;
                }
                $this->$versionDateColumn = date('d.m.Y H:i:s');

                //force insert instead of saving
                $this->primaryKey = null;        
                $this->setIsNewRecord(true);
                $this->setScenario('insert');
            }
            else
                $this->$versionDateColumn = trim($this->$versionDateColumn) ? date('d.m.Y H:i:s', strtotime($this->$versionDateColumn)) : date('d.m.Y H:i:s');

            $this->model()->updateAll(array($isActualColumn => 0), $this->getDeleteCriteria());
        }
        else 
            return $this->delete();

        return parent::save($runValidation, $attributes);
    }
    
    protected function afterSave()
    {
        $keyColumn = $this->_keyColumn;
        if ($this->$keyColumn == 0)
            $this->model()->updateByPk($this->primaryKey, array($keyColumn => $this->primaryKey));
        parent::afterSave();
    }
    
    public function setSubKeyColumn($value)
    {
        $subKeyColumn = $this->_subKeyColumn;
        $this->$subKeyColumn = $value;
    }
    
    public function getActuality()
    {
        $actualityColumn = $this->_actualityColumn;
        return $this->$actualityColumn;
    }
    
    public function setActuality($actuality)
    {
        $actualityColumn = $this->_actualityColumn;
        $this->$actualityColumn = $actuality;
    }
    
    public static function resetActuality($array)
    {
        foreach($array as $item)
            $item->setActuality(0);
    }
}
?>