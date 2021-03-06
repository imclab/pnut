<?php
class Model_Tag extends Model_Tree implements Countable
{

    protected $_table = 'tags';
	protected $_parent_field = 'syn_id';

    protected $_attributes = array(
        'name'   => 'name',
        'parent' => 'syn_id',
    );

    protected $_fields = array(
        'id'     => Model::TYPE_INTEGER,
        'name'   => Model::TYPE_STRING,
        'syn_id' => Model::TYPE_INTEGER,
    );

	private $_total = null;
	private static $_auto_create = false;

    protected $_list_class_name = 'Model_List_Tag';


	public function setAutoCreate($value = true) { self::$_auto_create = (bool)$value; }

	public function __construct(Storage_Db $db, $id = null)
	{
		if (is_string($id) && !is_numeric($id)) $id = array( 'name' => $id );
		parent::__construct($db, $id);
		if (self::$_auto_create && !$this->getId() && is_array($id) && isset($id['name']))
		{
			$this->name = $id['name'];
			$this->save();
		}
	}

	private function getModelsList($objname, $page = 0)
	{
		$model = Model_List::create($this->_db, $objname);
		$table = $model->getTable();
		$result = $this->_db->select(array('tag_relations', $table), "{$table}.*", "tag_relations.obj_type = '$table' and tag_relations.obj_id = {$table}.id and tag_relations.tag_id = ".$this->getId());
		$model->find($result);
		return $model;
	}

	public function getArticles($page = 0)
	{
		return $this->getModelsList('article', $page);
	}

	public function getTopics($page = 0)
	{
		return $this->getModelsList('topic', $page);
	}

	public function __toString()
	{
		return (string)$this->name;
	}

	public function parseData(array $values)
	{
		if (isset($values['total'])) $this->_total = (int)$values['total'];
		return parent::parseData($values);
	}

	public function count()
	{
		if ($this->_total === null)
		{
			$result = $this->_db->select('tag_relations', 'COUNT(*) AS total', array( 'tag_id' => $this->getId() ))->fetchFirst();
			$this->_total = (int)$result['total'];
		}
		return $this->_total;
	}

	public function tagModel(Model_Tagged $model)
	{
		$this->_db->insert('tag_relations', array( 'tag_id' => $this->getId(), 'obj_type' => $model->getTable(), 'obj_id' => $model->getId() ));
	}

	public function untagModel(Model_Tagged $model, $alltags = false)
	{
		$cond = array( 'obj_type' => $model->getTable(), 'obj_id' => $model->getId() );
		if (!$alltags) $cond['tag_id'] = $this->getId();
		$this->_db->delete('tag_relations', $cond);
	}

	public function regenerate($parent = 0, $state = 0)
	{
		return;
	}

	public function remove()
	{
		$id = $this->getId();
		if ($id)
		{
			parent::remove();
			$this->_db->delete('tag_relations', array( 'tag_id' => $id ));
		}
	}
}
?>
