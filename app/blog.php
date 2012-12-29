<?php

class Blog  {

	public $posts, $view, $layout, $db, $fw;

	function __construct() {
		$this->fw = Base::instance();
		$this->db = new DB\Mongo('mongodb://127.0.0.1:27017/blog', 'blog');
		$this->layout = ($this->fw->get('AJAX')) ? 'layouts/blank.html' : 'layouts/common.html' ;
		$this->mimetype = 'text/html';
		$this->view = 'loop';
		
		/*Side bar */
		/*Tags list menu */
		$this->fw->set('tagsList', $this-> getTags());
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
		$pageNumber = (($pageNumber-1) < 0 ) ? 0 : $pageNumber - 1;
		
		$by = $this->fw->get('PARAMS.slugId');
		$slugValue = $this->fw->get('PARAMS.slugValue');
		
		switch($by){
			default:
				$condition = array('state' => 1);
				$options = array('offset'=>ceil($pageNumber*$postPerPage),'limit'=>$postPerPage, 'order'=> array('date'=>-1));
				$link = '';
			break;
			
			case 'tag':
				$condition = array('tags' => array('$in' => array($slugValue)));
				$options = array('offset'=>ceil($pageNumber*$postPerPage),'limit'=>$postPerPage);
				$link = 'by/tag/'.$slugValue.'/';
			break;
				
			case 'date':
				die('date');
			break;
		}
		
		$posts = new DB\Mongo\Mapper($this->db, 'posts');
		$nbPosts = count($posts->find($condition));		
		$activePosts = $posts->find($condition,$options);
		$nbPageMax = ceil($nbPosts/$postPerPage);
		
		if(count($activePosts)==0)
			echo $this->fw->reroute('/');
		
		$this->fw->mset(array(
			'posts' => $activePosts,
			'pagination' => array(
					'limit' => $postPerPage,
					'active' => $pageNumber,
					'count' => $nbPageMax,
					'link' => $link
				)
			)
		);
	}

	function post() {

		$slugid = tools::slug($this->fw->get('PARAMS.slugid'), 24);
		$this-> view = 'post';
		$posts = new DB\Mongo\Mapper($this->db, 'posts');
		$this->fw->set('post', $posts->findOne(array('_id' => new Mongoid($slugid))));
		
	}

	function PostByTag() {
		$posts = new DB\Mongo\Mapper($this->db, 'posts');
		$this->fw->set('posts', $posts-> find(array('tags' => array('$in' => array($this->fw->get('PARAMS.slugid'))))));
	}

	function postToRSS() {
		$posts = new DB\Mongo\Mapper($this->db, 'posts');
		$this->fw->set('posts', $posts-> find(array('state' => 1), array('$oderby', array('date' => 1)), array('limit' => 5)));
		$this-> layout = 'layouts/posts.rss';
		$this->mimetype = 'application/rss+xml';
	}

	/*
	 *
	 * Login stuff
	 *
	 * */

	function logout() {
		$this->fw->set('SESSION.messages', array('general' => array('success' => array())));
		$this->fw->push('SESSION.messages.general.success', 'Bye bye !');
		$this->fw->set('SESSION.user', false);
		$this->fw->reroute('/');
	}

	function auth() {
		$this->fw->clear('SESSION');

		tools::checkID();
		tools::checkPassword();

		if (!$this->fw->exists('SESSION.message.login.error')) {
			// No input error; check values
			if (preg_match('/^admin$/i', $_POST['id']) && preg_match('/^admin$/i', $_POST['pwd'])) {
				$this->fw->set('SESSION.user', $_POST['id']);
				$this->fw->set('SESSION.message.login.success', 'Welcome ' . $_POST['id']);
				$this->fw->reroute('/');
			} else
				$this->fw->set('SESSION.message.login.error', 'Invalid user ID or password');
		}
		$this-> login();
	}

	/*
	 * Blog's Post CRUD
	 *
	 * */
	 
	function isAuth() {
		$session = $this->fw->get('SESSION');
		return isset($session['user']) && $session['user'] != '';
	}

	function createPost() {
		
		if ($this-> isAuth()) {

			$this->fw->set('SESSION.messages', array('general' => array('warning' => array(), 'error' => array(), 'success' => array())));

			$this-> view = 'postForm';
			$this->fw->scrub($_POST, $this->fw->get('allowed_tags'));

			if (isset($_POST['createPost']) == '1') {

				$postData = $this-> isValidPost($_POST);
				$post = new DB\Mongo\Mapper($this->db, 'posts');
				if ($postData !== false) {

					$postData['date'] = new MongoDate(strtotime($postData['date']));
					$postData['state'] = (int)$postData['state'];
					$postData['tags'] = explode(',', preg_replace('/\s+/', '', $postData['tags']));
					$postData['replies'] = array();
					unset($postData['createPost']);
					unset($postData['smbtBt']);
					$this->fw->set('postData', $postData);
					
					$post-> copyFrom('postData');
					$post-> save();
					$this->updateTagsList();
					
					$this->fw->push('SESSION.messages.general.success', ($postData['state'] == 1) ? 'Post saved as published' : 'Post saved as draft');
					$this->fw->reroute('/');

				} else {
					
					$post->title = trim($_POST['title']);
					$post->date = new MongoDate(strtotime($_POST['date']));
					$post->content = trim($_POST['content']);
					$post-> tags = explode(',', trim($_POST['tags']));
					
					$this->fw->set('postData', $post);
				}
			}

		} else {
			$this->fw->push('SESSION.messages.general.error', 'You have no rights to go there !');
			$this->fw->reroute('/');
		}
	}

	function updatePost() {

		$this->fw->set('SESSION.messages', array('general' => array('warning' => array(), 'error' => array(), 'success' => array())));
		$this-> view = 'postForm';

		if ($this-> isAuth()) {

			$slugid = tools::slug($this->fw->get('PARAMS.slugid'), 24);
			$post = new DB\Mongo\Mapper($this->db, 'posts');
			$this->fw->set('postData', $post->load(array('_id' => new Mongoid($slugid))));

			if (!$post->dry()) {

				if (isset($_POST['createPost']) == '1') {

					if( ($postData = $this-> isValidPost($_POST)) !== FALSE){
						$postData['date'] = new MongoDate(strtotime($postData['date']));
						$postData['state'] = (int)$postData['state'];
						$postData['tags'] = explode(',', preg_replace('/\s+/', '', $postData['tags']));
	
						unset($postData['createPost']);
						unset($postData['smbtBt']);
						unset($postData['id']);
	
						$this->fw->set('postQuery', $postData);
	
						$post-> copyFrom('postQuery');
						$post-> save();
						$this->updateTagsList();
						$this->fw->push('SESSION.messages.general.success', 'Post successfully updated !');
						$this->fw->reroute('/post/' . $slugid);
							
					}
				}
			} else {
				$this->fw->push('SESSION.messages.general.error', 'Post not found.');
				$this->fw->reroute('/');
			}
		} else {
			$this->fw->push('SESSION.messages.general.error', 'You have no rights to go there !');
			$this->fw->reroute('/');
		}
	}

	function deletePost() {
		$post = new DB\Mongo\Mapper($this->db, 'posts');
		$this->fw->set('SESSION.messages', array('general' => array('success' => array())));
		$slugid = tools::slug($this->fw->get('PARAMS.slugid'), 24);
		$post->load(array('_id' => new Mongoid($slugid)));
		$this->fw->push('SESSION.messages.general.success', 'Post "' . $post-> title . '" id: "' . $post-> _id . '" successfully deleted !');
		$post->erase();
		$this->updateTagsList();
		$this->fw->reroute('/');
	}

	function toggleStatePost() {
		$post = new DB\Mongo\Mapper($this->db, 'posts');
		$this->fw->set('SESSION.messages', array('general' => array('success' => array())));
		$slugid = tools::slug($this->fw->get('PARAMS.slugid'), 24);
		$post->load(array('_id' => new Mongoid($slugid)));
		$post->state = ($post->state == 1 )? 0 : 1;
		$post->save();
		
		$state = ($post->state == 1) ? 'published' : 'draft';
		$this->fw->push('SESSION.messages.general.success', 'Post "' . $post-> title . '" id: "' . $post-> _id . '" successfully set as ' . $state . ' !');
		$this->fw->reroute('/');
	}

	/*
	 * ~ Helpers
	 *
	 * */
	function updateTagsList(){
		
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
		$posts->mapReduce(
			array(
				"mapreduce" => "posts", 
				"map" => $map, 
				"reduce" => $reduce, 
				"query" => array("state" => 1), 
				"out" => "postsTags"
			)
		);
	}
	
	
	function getTags() {
		$tags = new DB\Mongo\Mapper($this->db, 'postsTags');
		return $tags->find(); 
	}

	function isValidPost($post) {
		if (is_array($post) && count($post) > 0) {
			$isValidPost = true;
			foreach ($post as $fName => $fValue) {
				switch (trim($fName)) {
					case 'title' :
						if (strlen($post['title']) < 1) {
							$this->fw->push('SESSION.messages.general.error', 'Post title is missing.');
							$isValidPost = false;
							break 2;
						}
						break;

					case 'date' :
						if (strlen($post['date']) < 1) {
							$post['date'] = new MongoDate();
							$this->fw->push('SESSION.messages.general.warning', 'Date as been filled up automatically.');
							break;
						} else if (($validDate = strtotime($post['date'])) === false) {
							$this->fw->push('SESSION.messages.general.error', 'Date format is invalid, use <strong>' . date($this->fw->get('blog.date.set')) . '</strong>');
							$isValidPost = false;
							break 2;
						}
						break;

					case 'content' :
						if (strlen($post['content']) < 1) {
							$this->fw->push('SESSION.messages.general.error', 'Post content is missing.');
							$isValidPost = false;
							break 2;
						}
						break;

					case 'tags' :
						if (strlen($post['tags']) < 1) {
							$post['tags'] = 'untagged';
							$this->fw->push('SESSION.messages.general.warning', 'Post automaticly tagged as "untagged"');
						}
						break;
				}
			}
		} else {
			$isValidPost = false;
		}
		return ($isValidPost) ? $post : false;
	}
	
	function documentation(){
		$this->fw->set('blog.title','Documentation');
		$this->fw->set('content','page');
		$this->fw->set('pageContent', 'documentation');
		
		
		include_once "markdown.php";
		$md = Markdown($this->fw->read('tmp/readme.md'));
		$this->fw->set('readme', $md);
	}

	function about(){
		$this->fw->set('blog.title','About this demo');
		$this->fw->set('content','page');
		$this->fw->set('pageContent', 'about');
	}
	
	function afterRoute() {
		
		$this->fw->set('view', $this-> view);
		echo Template::instance()->render($this-> layout, $this->mimetype);
		$this->fw->set('SESSION.messages', array());
	}

}
