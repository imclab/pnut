<?php
/**
 * @package Models
 * @subpackage Content
 * Topic model
 */
class Model_Topic extends Model_TraversedTree implements Model_Rightful, Model_Trashable
{
    protected $_fields = array(
        'id'                 => Model::TYPE_INTEGER,
        'parent_id'          => Model::TYPE_INTEGER,
        'name'               => Model::TYPE_STRING,
        'title'              => Model::TYPE_STRING,
        'description'        => Model::TYPE_STRING,
        //'owner_id'         => Model::TYPE_INTEGER,
        //'group_id'         => Model::TYPE_INTEGER,
        //'rights'           => Model::TYPE_INTEGER,
        'lside'              => Model::TYPE_INTEGER,
        'rside'              => Model::TYPE_INTEGER,
        'subtopic_id'        => Model::TYPE_INTEGER,
        'article_id'         => Model::TYPE_INTEGER,
        'items_per_page'     => Model::TYPE_INTEGER,
        'flags'              => Model::TYPE_SET,
        'type'               => Model::TYPE_ENUM,
        'articles_sort'      => Model::TYPE_ENUM,
        'articles_sort_desc' => Model::TYPE_BOOLEAN,
    );

    protected $_attributes = array(
        //'id'               => 'id',
        'parent'             => 'parent_id',
        'name'               => 'name',
        'title'              => 'title',
        'description'        => 'description',
        //'owner'            => 'owner_id',
        //'group'            => 'group_id',
        //'rights'           => 'rights',
        'lside'              => 'lside',
        'rside'              => 'rside',
        'subtopic'           => 'subtopic_id',
        'article'            => 'article_id',
        'flags'              => 'flags',
        'type'               => 'type',
        'items_per_page'     => 'items_per_page',
        'articles_sort'      => 'articles_sort',
        'articles_sort_desc' => 'articles_sort_desc',
    );

    protected $_table = 'topics';
    protected $_list_class_name = 'Model_List_Topic';

	protected $_views = array(
			'visible' => 'visible_topics',
			'nonremoved' => 'nonremoved_topics',
		);

	private $_rights = null;

	public function getArticle($id)
	{
		$field = is_numeric($id)? 'id': 'name';
		return new Model_Article($this->_db, array( 'topic_id' => $this->getId(), $field => $id ));
	}

    public function getArticles($page = 0)
    {
		if ($this->getId())
		{
			if ($this->isRecursive())
			{
				$id = $this->getDescendantsId();
				$id[] = $this->getId();
			}
			else
			{
				$id = $this->getId();
			}
			$desc = $this->articles_sort_desc? " DESC": "";
			return new Model_List_Article($this->_db, array( 'topic_id' => $id ), $this->items_per_page, $page, "{$this->articles_sort}{$desc}, {$this->_pk}{$desc}");
		}
		else
		{
			return array();
		}
    }

    public function getParent()
    {
        return $this->parent? new Model_Topic($this->_db, $this->parent): null;
    }

	public function getSiblings()
	{
		return new Model_List_Topic($this->_db, array( 'parent_id' => $this->parent ));
	}

	public function isMyArticle(Model_Article $article)
	{
		return $this->getId() == $article->topic;
	}

    public function getDefaultSubtopic()
    {
		if ($this->subtopic)
		{
			$result = new Model_Topic($this->_db, $this->subtopic);
			if (!$this->isAncestorOf($result)) $result = null;
		}
		else
		{
			$result = null;
		}
        return $result;
    }

	public function getDefaultArticle()
	{
		if ($this->article)
		{
			$result = new Model_Article($this->_db, $this->article);
			if (!$this->isMyArticle($result)) $result = null;
		}
		else
		{
			$result = null;
		}
		return $result;
	}

    public function __toString()
    {
        return $this->name;
    }

	public function isVisible()
	{
        return !array_intersect($this->flags, array('hidden', 'removed'));
	}

	public function isArchived()
	{
		return in_array('archived', $this->flags);
	}

	public function isRecursive()
	{
		return in_array('recursive', $this->flags);
	}

	public function isRemoved()
	{
		return in_array('removed', $this->flags);
	}

    public function validate(array $attributes = array())
    {
        $errors = parent::validate($attributes);

        if (!strlen($this->name))
            $errors['name'] = _('Name must not be empty');
        elseif (preg_match('/[^0-9A-Za-z_-]/', $this->name))
            $errors['name'] = _('Name must contain symbols from set: A-Z, a-z, 0-9, "-", "_"');

        if (!strlen($this->title))
            $errors['title'] = 'Title must not be empty';

        $subtopics = $this->getDescendantsId();
        if ($this->subtopic && !in_array($this->subtopic, $subtopics))
            $errors['subtopic'] = _('Default subtopic must be child of this topic');

        return $errors;
    }

	public function getRights()
	{
		if (!$this->_rights) $this->_rights = new Model_Rights($this);
		return $this->_rights;
	}

	public function getTags()
	{
		return Model_List_Tag::getModelTags($this->_db, $this);
	}
}
?>
