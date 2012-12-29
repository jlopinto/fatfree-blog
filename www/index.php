<?php
$f3=require('../lib/base.php');

$f3->set('DEBUG', 3);
$f3->set('UI','ui/');
$f3->set('CACHE', FALSE);
$f3->set('db', 'plop');
$f3->set('AUTOLOAD','../app/');


$f3->set('content', 'blog');

/*
 * Application data
 * */
$f3->set('blog.title','Blog/RSS Demo');
$f3->set('blog.description','...');
$f3->set('blog.post.perpage', 4);
$f3->set('blog.post.allowed_tags','p; br; ul; li; code');
$f3->set('blog.date.read','F j, Y, g:i a');
$f3->set('blog.date.set','m/d/Y');
$f3->set('blog.date.rss','D, d M Y G:i:s T');
$f3->set('blog.date.rss','D, d M Y H:i:s O');

/*
 * Application routes
 * 
 * */
$f3->route('GET /', 'blog->index');
$f3->route('GET /page/@pageNumber', 'blog->index');

$f3->route('GET /by/@slugId/@slugValue', 'blog->index');
$f3->route('GET /by/@slugId/@slugValue/page/@pageNumber', 'blog->index');


$f3->route('GET /post/@slugid', 'blog->post');
$f3->route('GET /post/@slugid/@slugname', 'blog->post');

$f3->route('POST|GET /post/create', 'blog->createPost');
$f3->route('POST|GET /post/update/@slugid', 'blog->updatePost');
$f3->route('GET /post/state/@slugid', 'blog->toggleStatePost');
$f3->route('GET /post/delete/@slugid', 'blog->deletePost');

$f3->route('GET /rss', 'blog->postToRSS');

$f3->route('POST /login', 'blog->auth');
$f3->route('GET /logout', 'blog->logout');


$f3->route('GET /documentation', 'blog->documentation');
$f3->route('GET /about', 'blog->about');
$f3->route('GET /updateArchivesList', 'blog->updateArchivesList');
/*
 * Utils routes
 * 
 * */
 
/*$f3->route('GET /minify/@type',
    function() use($f3) {
        echo Web::instance()->minify($_GET['files']);
    },
    6400
);*/
$f3->run();
?>