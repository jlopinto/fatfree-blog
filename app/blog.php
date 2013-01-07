<?php

class Blog {

	public $posts, $view, $layout, $db, $fw, $isAuth;

	function __construct() {
		$this->fw = Base::instance();

		$this->db = new DB\Mongo($this->fw->get('dburi') . '/' . $this->fw->get('dbname'), $this->fw->get('dbname'));

		$this->layout = ($this->fw->get('AJAX')) ? 'layouts/blank.html' : 'layouts/common.html';
		$this->mimetype = 'text/html';
		$this->view = 'components/loop';

		$this->isAuth = $this->fw->exists('SESSION.user') && $this->fw->get('SESSION.user') != '';

		/*Side bar */
		/*Tags list menu */
		$this->fw->set('tagsList', $this->getTags());
	}

	/*
	 *
	 * Blog's rendered views/pages
	 *
	 * */

	function index() {

		/* pagination stuff */
		$postPerPage = $this->fw->get('blog.post.perpage');
		$pageNumber = $this->fw->get('PARAMS.pageNumber');

		$pageNumber = ($pageNumber === null) ? '0' : $pageNumber;
		if (!ctype_digit($pageNumber)) {
			$this->fw->push('SESSION.messages.error', 'The requested page wasn\'t found');
			$this->fw->reroute('/page/' . tools::intMe($pageNumber));
		}

		$pageNumber = ($pageNumber - 1 < 0) ? 0 : $pageNumber - 1;

		/* default displayed posts states  */
		$defaultStates = $this->isAuth ? 0 : 1;

		$by = $this->fw->get('PARAMS.slugId');
		$slugValue = $this->fw->get('PARAMS.slugValue');

		$options = array('order' => array('date' => -1));

		switch($by) {
			default :
				$condition = array('state' => array('$gte' => $defaultStates));
				$link = '';
				break;

			case 'tag' :
				$condition = array('tags' => array('$in' => array($slugValue)));
				$link = 'by/tag/' . $slugValue . '/';
				break;

			case 'date' :
				die('date');
				break;
		}

		$posts = new DB\Mongo\Mapper($this->db, 'posts');
		$activePosts = $posts->paginate($pageNumber, $postPerPage, $condition, $options);

		if (count($activePosts['subset']) === 0) {
			$this->fw->push('SESSION.messages.error', 'The requested page wasn\'t found');
			$this->fw->reroute('/page/' . ($activePosts['count'] || 1));
		}

		$this->fw->mset(array('posts' => $activePosts['subset'], 'pagination' => array('limit' => $postPerPage, 'active' => $pageNumber, 'count' => $activePosts['count'], 'link' => $link)));
	}

	function post() {

		$this->view = 'components/post';
		$slugid = tools::slug($this->fw->get('PARAMS.slugid'), 24);
		$posts = new DB\Mongo\Mapper($this->db, 'posts');

		$post = $posts->findOne(array('_id' => new Mongoid($slugid)));
		if ($post->state === 0 && !$this->isAuth) {
			$this->fw->push('SESSION.messages.error', 'You have no rights to go there !');
			$this->fw->reroute('/');
		}
		$this->fw->set('post', $post);

	}

	function PostByTag() {
		$posts = new DB\Mongo\Mapper($this->db, 'posts');
		$this->fw->set('posts', $posts->find(array('tags' => array('$in' => array($this->fw->get('PARAMS.slugid'))))));
	}

	function postToRSS() {
		$posts = new DB\Mongo\Mapper($this->db, 'posts');
		$this->fw->set('posts', $posts->find(array('state' => 1), array('$oderby', array('date' => 1)), array('limit' => 5)));
		$this->layout = 'layouts/posts.rss';
		$this->mimetype = 'application/rss+xml';
	}

	/*
	 *
	 * Login / Logout
	 *
	 * */

	function logout() {
		$this->fw->push('SESSION.messages.success', 'Bye bye !');
		$this->fw->clear('SESSION');
		$this->fw->reroute('/');
	}

	function auth() {

		$this->fw->clear('SESSION');
		tools::checkID();
		tools::checkPassword();

		if (!$this->fw->exists('SESSION.messages.error')) {

			// No input error; check values
			if (preg_match('/^' . $this->fw->get('authlogin') . '$/i', $_POST['id']) && preg_match('/^' . $this->fw->get('authpwd') . '$/i', $_POST['pwd'])) {
				$this->fw->set('SESSION.user', $_POST['id']);
				$this->fw->set('SESSION.messages.success', array('Welcome !'));
				$this->fw->reroute('/');
			} else {
				$this->fw->set('SESSION.messages.error', array('Invalid user ID or password'));
			}
		}
		$this->fw->reroute('/');
	}

	/*
	 * Blog's Post CRUD
	 *
	 * */

	function createPost() {

		if ($this->isAuth) {
			$this->view = 'components/postForm';
			$this->fw->scrub($_POST, $this->fw->get('allowed_tags'));

			if (isset($_POST['createPost']) == '1') {

				$postData = $this->isValidPost($_POST);
				$post = new DB\Mongo\Mapper($this->db, 'posts');
				if ($postData !== false) {

					$postData['date'] = new MongoDate(strtotime($postData['date']));
					$postData['state'] = (int)$postData['state'];
					$postData['tags'] = explode(',', preg_replace('/\s+/', '', $postData['tags']));
					$postData['replies'] = array();
					unset($postData['createPost']);
					unset($postData['smbtBt']);
					$this->fw->set('postData', $postData);

					$post->copyFrom('postData');
					$post->save();
					$this->updateTagsList();

					$this->fw->push('SESSION.messages.success', ($postData['state'] == 1) ? 'Post saved as published' : 'Post saved as draft');
					$this->fw->reroute('/');

				} else {

					$post->title = trim($_POST['title']);
					$post->date = new MongoDate(strtotime($_POST['date']));
					$post->content = trim($_POST['content']);
					$post->tags = explode(',', trim($_POST['tags']));

					$this->fw->set('postData', $post);
				}
			}

		} else {
			$this->fw->push('SESSION.messages.error', 'You have no rights to go there !');
			$this->fw->reroute('/');
		}
	}

	function updatePost() {

		if ($this->isAuth) {
			$this->view = 'components/postForm';
			$slugid = tools::slug($this->fw->get('PARAMS.slugid'), 24);
			$post = new DB\Mongo\Mapper($this->db, 'posts');
			$this->fw->set('postData', $post->load(array('_id' => new Mongoid($slugid))));

			if (!$post->dry()) {

				if (isset($_POST['createPost']) == '1') {

					if (($postData = $this->isValidPost($_POST)) !== FALSE) {
						$postData['date'] = new MongoDate(strtotime($postData['date']));
						$postData['state'] = (int)$postData['state'];
						$postData['tags'] = explode(',', preg_replace('/\s+/', '', $postData['tags']));

						unset($postData['createPost']);
						unset($postData['smbtBt']);
						unset($postData['id']);

						$this->fw->set('postQuery', $postData);

						$post->copyFrom('postQuery');
						$post->save();
						$this->updateTagsList();
						$this->fw->push('SESSION.messages.success', 'Post successfully updated !');
						$this->fw->reroute('/post/' . $slugid);

					}
				}
			} else {
				$this->fw->push('SESSION.messages.error', 'Post not found.');
				$this->fw->reroute('/');
			}
		} else {
			$this->fw->push('SESSION.messages.error', 'You have no rights to go there !');
			$this->fw->reroute('/');
		}
	}

	function deletePost() {
		$post = new DB\Mongo\Mapper($this->db, 'posts');
		$slugid = tools::slug($this->fw->get('PARAMS.slugid'), 24);
		$post->load(array('_id' => new Mongoid($slugid)));
		$this->fw->push('SESSION.messages.success', 'Post "' . $post->title . '" id: "' . $post->_id . '" successfully deleted !');
		$post->erase();
		$this->updateTagsList();
		$this->fw->reroute('/');
	}

	function togglePostState() {
		$post = new DB\Mongo\Mapper($this->db, 'posts');

		$slugid = tools::slug($this->fw->get('PARAMS.slugid'), 24);
		$post->load(array('_id' => new Mongoid($slugid)));
		$post->state = ($post->state == 1) ? 0 : 1;
		$post->save();

		$state = ($post->state == 1) ? 'published' : 'draft';
		$this->fw->push('SESSION.messages.success', 'Post "' . $post->title . '" id: "' . $post->_id . '" successfully set as ' . $state . ' !');
		$this->fw->reroute('/');
	}

	/*
	 * ~ Helpers
	 *
	 * */
	function updateTagsList() {

		$map = new MongoCode("
			function() {
				 if (!this.tags) {
			        return;
			    }
			
			    for (index in this.tags) {
			        emit(this.tags[index], 1);
			    }
			}
		");

		$reduce = new MongoCode("
			function(previous, current) { 
			   var count = 0;

			    for (index in current) {
			        count += current[index];
			    }
			
			    return count;
			}
		");
		$posts = new DB\Mongo\Mapper($this->db, 'posts');
		$posts->mapReduce(array("mapreduce" => "posts", "map" => $map, "reduce" => $reduce, "query" => array("state" => 1), "out" => "postsTags"));
	}

	function features() {
		$this->view = 'features';
	}

	function cookbook() {
		$this->view = 'cookbook';
	}

	function covered() {

		$this->fw->set('covered', array( array('audit', false), array('auth', false), array('autoload', true), array('cache', true), array('geo', false), array('globals', true), array('hive', true), array('image', false), array('internals', true), array('jig', false), array('lexicon', false), array('log', false), array('matrix', false), array('mongo', true), array('openid', false), array('openid2', false), array('pingback', false), array('pingback2', false), array('redir', true), array('router', true), array('sql', false), array('template', true), array('unicode', false), array('web', true)));
		$this->view = 'covered';
	}

	function getTags() {
		$tags = new DB\Mongo\Mapper($this->db, 'postsTags');
		return $tags->find(array(), array('order' => array('value' => -1)));
	}

	function isValidPost($post) {
		if (is_array($post) && count($post) > 0) {
			$isValidPost = true;
			foreach ($post as $fName => $fValue) {
				switch (trim($fName)) {
					case 'title' :
						if (strlen($post['title']) < 1) {
							$this->fw->push('SESSION.messages.error', 'Post title is missing.');
							$isValidPost = false;
							break 2;
						}
						break;

					case 'date' :
						if (strlen($post['date']) < 1) {
							$post['date'] = new MongoDate();
							$this->fw->push('SESSION.messages.warning', 'Date as been filled up automatically.');
							break;
						} else if (($validDate = strtotime($post['date'])) === false) {
							$this->fw->push('SESSION.messages.error', 'Date format is invalid, use <strong>' . date($this->fw->get('blog.date.set')) . '</strong>');
							$isValidPost = false;
							break 2;
						}
						break;

					case 'content' :
						if (strlen($post['content']) < 1) {
							$this->fw->push('SESSION.messages.error', 'Post content is missing.');
							$isValidPost = false;
							break 2;
						}
						break;

					case 'tags' :
						if (strlen($post['tags']) < 1) {
							$post['tags'] = 'untagged';
							$this->fw->push('SESSION.messages.warning', 'Post automaticly tagged as "untagged"');
						}
						break;
				}
			}
		} else {
			$isValidPost = false;
		}
		return ($isValidPost) ? $post : false;
	}

	function documentation() {
		$this->fw->set('blog.title', 'Documentation');
		$this->fw->set('content', 'page');
		$this->fw->set('pageContent', 'documentation');

		include_once "markdown.php";
		$md = Markdown($this->fw->read('tmp/readme.md'));
		$this->fw->set('readme', $md);
	}

	function about() {
		$this->fw->set('blog.title', 'About this demo');
		$this->fw->set('content', 'page');
		$this->fw->set('pageContent', 'about');
	}

	function beforeRoute() {
		if (!$this->fw->exists('SESSION.messages')) {
			$this->fw->set('SESSION.messages', array('success' => array(), 'warning' => array(), 'error' => array()));
		}
	}

	function afterRoute() {
		$this->fw->set('view', $this->view);

		echo Template::instance()->render($this->layout, $this->mimetype);
		$this->fw->set('SESSION.messages', array('success' => array(), 'warning' => array(), 'error' => array()));
	}

}
