<?php
/**
 * @package Site
 * @subpackage Admin
 * Admin controller for articles management
 */
class Controller_Admin_Article extends Controller_Admin_Content
{
    protected $_actions = array(
        'default' => 'actionDefault',
        'edit'    => 'actionEdit',
        'new'     => 'actionNew',

        'remove'  => 'actionRemove',
        'move'    => 'actionMove',
        'rename'  => 'actionRename',
        'dettach' => 'actionDettach',
		'reorder' => 'actionReorder',

		'tag'     => 'actionTag',
    );

    private function saveArticle(Model_Article $article, View_Html $view)
    {
        if (isset($_REQUEST['save']))
        {
            foreach (array("published", "archived") as $name)
            {
                $_POST[$name] = Filter_Date::fromString($_POST[$name]);
            }
			if (!isset($_POST['flags'])) $_POST['flags'] = array();
            $article->setData($_POST);

            if (!($errors = $article->validate()))
            {
                $article->save();
				$article->getRights()->setRights($_POST['rights'], $_POST['owner'], $_POST['group'])->save();

				$article->dropTags();
				$tags = isset($_POST['tag'])? $_POST['tag']: (isset($_POST['tags'])? explode(", ", $_POST['tags']): null);
				Model_Tag::setAutoCreate();
				if ($tags) $article->addTags($tags);

                if ($_FILES && $_FILES["attach"])
                {
					$store = $this->getStorage();
                    foreach ($_FILES["attach"]["name"] as $index => $name)
                    {
                        $attachment = new Model_Attachment($store);
                        $file = array(
                            'name'     => $name,
                            'tmp_name' => $_FILES['attach']['tmp_name'][$index],
                            'error'    => $_FILES['attach']['error'][$index],
                            'size'     => $_FILES['attach']['size'][$index],
                            'type'     => $_FILES['attach']['type'][$index],
                        );

                        if ($attachment->uploadFile($file))
                        {
                            $attachment->attachTo($article);
                            $attachment->save();
                        }
                    }
                }

                $view->redir('Admin_Topic', 'default', array( 'id' => $article->topic ));
                return true;
            }
            $view->errors = $errors;
        }
        return false;
    }

    public function actionDefault($params)
    {
        $view = new View_Html('manage/default');
        $view->redir('Admin_Topic');
        return $view;
    }

    public function actionNew($params)
    {
        $store = $this->getStorage();
        $view = $this->htmlView('edit_article');
        $view->article = new Model_Article($store);
        $view->topic   = new Model_Topic($store, $params['id']);
		$this->canPerform($view->topic, "edit");

        if ($this->saveArticle($view->article, $view))
            return $view;

        $view->users   = new Model_List_User($store, '');
        $view->groups  = new Model_List_Group($store, '');

        $view->article->published = time();
        $view->article->archived = $view->article->published + (10*365+2)*86400;
        $view->article->flags = array();
        $view->article->type  = $view->topic->type;
        $view->article->views = 0;
        $view->article->topic = (int)$params['id'];
        $view->article->items_per_page = 20;
		$view->article->author = $this->getAuthorizator()->getUser()->getId();
        $view->article->getRights()->setRights(array('ur','uw','ux','gr','gw','or'), $this->getAuthorizator()->getUser(), $this->getAuthorizator()->getUser()->getGroup());
        return $view;
    }

    public function actionEdit($params)
    {
        $view = $this->htmlView("edit_article");
        if ($params["id"])
        {
            $store = $this->getStorage();
            $view->article = new Model_Article($store, $params["id"]);
            $this->canPerform($view->article, "edit");

            if ($this->saveArticle($view->article, $view))
                return $view;
           
            $view->users  = new Model_List_User($store, '');
            $view->groups = new Model_List_Group($store, '');
        } else {
            $view->redir("Admin_Topic");
        }
        return $view;
    }

    public function actionRemove($params)
    {
        $view = $this->ajaxView('article');
        $view->state = "failed";

        if ($params["id"])
        {
            $article  = new Model_Article($this->getStorage(), $params["id"]);
            $view->id = $article->getId();

            if ($view->id)
            {
                $this->canPerform($article, "delete");
                $this->canPerform($article->getTopic(), "edit");

                $view->state = "removed";
                try
                {
                    //$article->remove();
					Model_Trashcan::put($article);
                }
                catch (Exception $e)
                {
                    $view->state = "failed";
                    $view->error = $e->getMessage();
                }
            }
            else
            {
                $view->error = "Article not found.";
            }
        }
        else
        {
            $view->error = "Article ID is not set.";
        }
        return $view;
    }

    public function actionRename($params)
    {
        $view = $this->ajaxView('article');
        $view->state = "failed";

        if ($params["id"])
        {
            $article  = new Model_Article($this->getStorage(), $params["id"]);
            $view->id = $article->getId();

            if ($view->id)
            {
                $this->canPerform($article, "edit");
                $article->title = $_GET["title"];
                $article->name  = $_GET["name"];
                $view->title    = $article->title;
                $view->name     = $article->name;

                if ($errors = $article->validate())
                {
                    $view->error = "validation failed";
                    $view->errors = $errors;
                }
                else
                {
                    $view->state = "renamed";
                    try
                    {
                        $article->save();
                    }
                    catch (Exception $e)
                    {
                        $view->state = "failed";
                        $view->error = $e->getMessage();
                    }
                }
            }
        }
        return $view;
    }

    public function actionMove($params)
    {
        $view = $this->ajaxView('article');
        $view->state = "failed";

        if ($params["id"] && $params["targid"])
        {
            $article = new Model_Article($this->getStorage(), $params["id"]);
            $topic   = new Model_Topic($this->getStorage(), $params["targid"]);
            $view->id       = $article->getId();
            $view->topic_id = $topic->getId();

            if ($view->id && $view->topic_id)
            {
                if ($article->topic != $topic->getId())
                {
                    $this->canPerform($article, "edit");
                    $this->canPerform($topic, "edit");

					$view->state = "moved";
					$article->topic = $topic->getId();
					try
					{
						$article->save();
					}
					catch (Exception $e)
					{
						$view->state = "failed";
						$view->error = $e->getMessage();
					}
                }
                else
                {
                    $view->state = "notmoved";
                }
            }
            else
            {
                $view->error = "Article or topic not found.";
            }
        }
        else
        {
            $view->error = "Article or topic ID is not set.";
        }
        return $view;
    }

    public function actionDettach($params)
    {
        $view = $this->ajaxView('file');
        $view->state = 'failed';

        if ($params['id'])
        {
            $attachment = new Model_Attachment($this->getStorage(), $params['id']);
            $view->id = $attachment->getId();

            if ($view->id)
            {
				$this->canPerform($attachment, 'remove');
				$this->canPerform($attachment->getArticle(), 'edit');
                $view->state = 'removed';
                try
                {
                    $attachment->remove();
                }
                catch (Exception $e)
                {
                    $view->state = 'failed';
                    $view->error = $e->getMessage();
                }
            }
            else
            {
                $view->error = 'Attachment not found.';
            }
        }
        else
        {
            $view->error = 'Attachment ID is not set.';
        }

        return $view;
    }

	public function actionReorder($params)
	{
		$view = $this->ajaxView('article');
		$view->state = 'failed';

		if ($params['id'] && $params['targid'])
		{
			$oarticle = new Model_Article($this->getStorage(), $params['id']);
			$tarticle = new Model_Article($this->getStorage(), $params['targid']);
			$view->id        = $oarticle->getId();
			$view->target_id = $tarticle->getId();

			if ($oarticle->isLoaded() && $tarticle->isLoaded())
			{
				$this->canPerform($oarticle, 'edit');
				$view->state = 'reordered';
				$view->place = $params['place'];

				switch ($params['place'])
				{
				case 'before':
					$method = 'insertBefore';
					break;
				case 'after':
					$method = 'insertAfter';
					break;
				default:
					if ($oarticle->isBefore($tarticle))
					{
						$method = 'insertAfter';
						$view->place = 'after';
					}
					else
					{
						$method = 'insertBefore';
						$view->place = 'before';
					}
				}

				try
				{
					$oarticle->$method($tarticle);
				}
				catch (Exception $e)
				{
					$view->state = 'failed';
					$view->error = $e->getMessage();
				}
			}
			else
			{
				$view->error = 'Article(s) not found.';
			}
		}
		else
		{
			$view->error = 'Article IDs are not set.';
		}
		return $view;
	}
}
?>
